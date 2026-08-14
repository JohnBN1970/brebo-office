(() => {
  'use strict';

  function decodeQuotedPrintable(value) {
    const input = String(value || '').replace(/=\r?\n/g, '');
    const bytes = [];
    const encoder = new TextEncoder();
    for (let i = 0; i < input.length; i++) {
      if (input[i] === '=' && /^[0-9A-F]{2}$/i.test(input.slice(i + 1, i + 3))) {
        bytes.push(parseInt(input.slice(i + 1, i + 3), 16));
        i += 2;
      }
      else {
        bytes.push(...encoder.encode(input[i]));
      }
    }
    try {
      return new TextDecoder('utf-8').decode(new Uint8Array(bytes));
    }
    catch (e) {
      return input.replace(/=([0-9A-F]{2})/gi, (_, hex) => String.fromCharCode(parseInt(hex, 16)));
    }
  }

  function decodeMimeWord(value) {
    return String(value || '').replace(/=\?UTF-8\?([BQ])\?([^?]+)\?=/gi, (_, mode, payload) => {
      try {
        if (mode.toUpperCase() === 'B') {
          const binary = atob(payload.replace(/\s+/g, ''));
          const bytes = Uint8Array.from(binary, (char) => char.charCodeAt(0));
          return new TextDecoder('utf-8').decode(bytes);
        }
        return decodeQuotedPrintable(payload.replace(/_/g, ' '));
      }
      catch (e) {
        return payload;
      }
    });
  }

  function htmlToText(value) {
    const parsed = new DOMParser().parseFromString(String(value || ''), 'text/html');
    parsed.querySelectorAll('style,script,head,title,meta,link').forEach((node) => node.remove());
    return (parsed.body.innerText || parsed.body.textContent || value)
      .replace(/\u00a0/g, ' ');
  }

  function cleanup(value) {
    let text = decodeMimeWord(String(value || '').replace(/\r\n/g, '\n'));

    // Legacy sources can contain several nested quoted-printable layers.
    for (let pass = 0; pass < 4 && /=([0-9A-F]{2})/i.test(text); pass++) {
      const decoded = decodeQuotedPrintable(text);
      if (decoded === text) break;
      text = decoded;
    }

    if (/<\/?[a-z][\s\S]*?>/i.test(text) || /&(?:nbsp|amp|lt|gt|quot|#\d+|#x[0-9a-f]+);/i.test(text)) {
      text = htmlToText(text);
    }

    return text
      // MIME and transport headers accidentally stored in the transcript.
      .replace(/^(?:MIME-Version|Content-(?:Type|Transfer-Encoding|Disposition|ID|Description)|X-Mailer|X-Priority):.*$/gmi, '')
      .replace(/^charset\s*=\s*["']?[^\s;"']+["']?;?\s*$/gmi, '')
      .replace(/^name\s*=\s*["']?[^\n"']+["']?\s*$/gmi, '')
      // Multipart boundaries and long generated separator/hash lines.
      .replace(/^--[-_=A-Za-z0-9.]{8,}(?:--)?\s*$/gm, '')
      .replace(/^[-_=#]{18,}\s*$/gm, '')
      // Residual HTML/CSS fragments from malformed MIME parts.
      .replace(/<!--[\s\S]*?-->/g, '')
      .replace(/\{\s*(?:font|margin|padding|color|background|line-height|text-align)[^}]*\}/gi, '')
      // Soft line breaks and leftover quoted-printable noise.
      .replace(/=\s*$/gm, '')
      .replace(/(?:=3D)+/gi, '=')
      .replace(/[ \t]+\n/g, '\n')
      .replace(/\n[ \t]+/g, '\n')
      .replace(/[ \t]{2,}/g, ' ')
      .replace(/\n{3,}/g, '\n\n')
      .trim();
  }

  function run() {
    const body = document.querySelector('.brebo-mail-reader__body');
    if (!body) return;
    const original = body.innerText || body.textContent || '';
    const cleaned = cleanup(original);
    if (cleaned && cleaned !== original.trim()) body.textContent = cleaned;
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', run, {once: true});
  }
  else {
    run();
  }
})();
