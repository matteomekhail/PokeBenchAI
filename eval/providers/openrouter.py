import base64
import re
import json
from dataclasses import dataclass
from typing import List, Dict, Optional

import requests
from tenacity import retry, stop_after_attempt, wait_exponential, retry_if_exception_type


class OpenRouterError(Exception):
    pass


@dataclass
class ImageInput:
    image_id: str
    url: Optional[str] = None
    b64: Optional[str] = None
    mime: str = "image/png"

    def to_content(self) -> List[Dict]:
        if self.url:
            return [
                {"type": "text", "text": self.image_id},
                {"type": "image_url", "image_url": {"url": self.url}},
            ]
        if self.b64:
            return [
                {"type": "text", "text": self.image_id},
                {"type": "image_url", "image_url": {"url": f"data:{self.mime};base64,{self.b64}"}},
            ]
        raise ValueError("ImageInput requires url or b64")


@retry(reraise=True, stop=stop_after_attempt(3), wait=wait_exponential(multiplier=1, min=1, max=8),
       retry=retry_if_exception_type((requests.RequestException, OpenRouterError)))
def call_openrouter(api_key: str, model: str, messages: List[Dict], max_tokens: int = 128,
                    temperature: float = 0.0, top_p: float = 1.0) -> Dict:
    headers = {
        "Authorization": f"Bearer {api_key}",
        "Content-Type": "application/json",
        "Accept": "application/json",
        "HTTP-Referer": "https://pokebenchai.test/",
        "X-Title": "PokeBenchAI",
        "User-Agent": "PokeBenchAI/1.0 (+https://pokebenchai.test/)"
    }
    payload = {
        "model": model,
        # usa schema OpenAI-compatible
        "messages": messages,
        "max_tokens": max_tokens,
        "temperature": temperature,
        "top_p": top_p,
        "response_format": {"type": "json_object"},
    }
    # usa direttamente chat/completions (schema OpenAI-compatible)
    url = "https://openrouter.ai/api/v1/chat/completions"
    resp = requests.post(url, headers=headers, json=payload, timeout=120)
    if resp.status_code >= 400:
        raise OpenRouterError(f"HTTP {resp.status_code}: {resp.text}")
    ct = resp.headers.get('Content-Type', '')
    if 'application/json' not in ct:
        raise OpenRouterError(f"Unexpected content-type {ct}: {resp.text[:500]}")
    return resp.json()


def classify_images(api_key: str, model: str, images: List[ImageInput], label_space: List[str], tolerant: bool = False) -> List[Dict]:
    results = []
    usage_totals = {"prompt_tokens": 0, "completion_tokens": 0, "total_tokens": 0, "input_images": 0}
    # Costruisci mapping normalizzato
    norm = lambda s: re.sub(r"[^a-z0-9]+", "", s.lower())
    label_norm_to_canon = {norm(l): l for l in label_space}
    label_space_norm = set(label_norm_to_canon.keys())

    label_list_json = json.dumps(label_space[:500])
    system = (
        "You are an image classifier. Return ONLY valid JSON with the exact schema: "
        '{"label": "<one_label>", "probs": {"<label>": <prob>}}. '
        "Choose the label ONLY from the provided list, with the exact spelling. No extra text."
    )
    for item in images:
        messages = [
            {"role": "system", "content": system},
            {"role": "user", "content": [
                {"type": "text", "text": (
                    f"Classify the image (id={item.image_id}) into ONE label among these (use exact spelling): {label_list_json}. "
                    "Return only JSON with the keys label and probs (with a few relevant labels)."
                )},
            ] + item.to_content()},
        ]
        data = call_openrouter(api_key, model, messages)
        # accumulate usage if present
        u = data.get("usage") or {}
        try:
            usage_totals["prompt_tokens"] += int(u.get("prompt_tokens", 0))
            usage_totals["completion_tokens"] += int(u.get("completion_tokens", 0))
            usage_totals["total_tokens"] += int(u.get("total_tokens", 0))
            # some providers expose input image count
            usage_totals["input_images"] += int(u.get("input_images", u.get("images", 0)) or 0)
        except Exception:
            pass
        # Estrai testo risposta (supporta varianti di schema: output_text o output[].content[])
        output_text = data.get("output_text")
        if not output_text:
            out = data.get("output")
            if isinstance(out, list) and out:
                parts = []
                for c in out[0].get("content", []) or []:
                    # alcuni provider usano {type:'output_text', text:'...'} o {type:'text', text:'...'}
                    txt = c.get("text") if isinstance(c, dict) else None
                    if txt:
                        parts.append(txt)
                output_text = "\n".join(parts) if parts else None
        if not output_text:
            # chat.completions schema
            choices = data.get("choices")
            if isinstance(choices, list) and choices:
                msg = choices[0].get("message", {})
                content = msg.get("content")
                if isinstance(content, str):
                    output_text = content
                elif isinstance(content, list):
                    parts = []
                    for c in content:
                        if isinstance(c, dict):
                            txt = c.get("text")
                            if txt:
                                parts.append(txt)
                    if parts:
                        output_text = "\n".join(parts)
        # Estrattore JSON di fallback (prima graffa completa)
        if output_text and not output_text.strip().startswith("{"):
            m = re.search(r"\{[\s\S]*\}", output_text)
            if m:
                output_text = m.group(0)
        if not output_text:
            output_text = json.dumps({"label": None, "probs": {}})
        try:
            parsed = json.loads(output_text)
        except Exception:
            if tolerant and isinstance(output_text, str) and output_text.strip():
                # Prova a mappare un nome libero alla label list
                text = output_text.lower()
                best = None
                for canon in label_space:
                    if canon in text:
                        best = canon
                        break
                if best:
                    results.append({"image_id": item.image_id, "probs": {best: 1.0}})
                    continue
            parsed = {"label": None, "probs": {}}
        # normalize probs to float dict
        probs_in = parsed.get("probs") or {}
        norm_probs: Dict[str, float] = {}
        for k, v in probs_in.items():
            if not isinstance(v, (int, float)):
                continue
            nk = norm(str(k))
            if nk in label_space_norm:
                canon = label_norm_to_canon[nk]
                norm_probs[canon] = float(v)
        # if label only, convert to 1.0
        if not norm_probs and parsed.get("label"):
            nk = norm(str(parsed["label"]))
            if nk in label_space_norm:
                canon = label_norm_to_canon[nk]
                norm_probs = {canon: 1.0}
        # tolerant fallback: search label substrings in textual output
        if not norm_probs and tolerant and output_text:
            text = output_text.lower()
            for canon in label_space:
                if canon in text:
                    norm_probs = {canon: 1.0}
                    break
        results.append({"image_id": item.image_id, "probs": norm_probs})
    return results, usage_totals

