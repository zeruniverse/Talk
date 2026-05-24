(function () {
  'use strict';

  const encoder = new TextEncoder();
  const decoder = new TextDecoder();

  function utf8Encode(value) {
    return encoder.encode(String(value));
  }

  function utf8Decode(bytes) {
    return decoder.decode(bytes);
  }

  function randomBytes(length) {
    const bytes = new Uint8Array(length);
    crypto.getRandomValues(bytes);
    return bytes;
  }

  function base64UrlEncode(bytes) {
    let binary = '';
    for (let i = 0; i < bytes.length; i++) {
      binary += String.fromCharCode(bytes[i]);
    }
    return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/g, '');
  }

  function base64UrlDecode(text) {
    let value = String(text).replace(/-/g, '+').replace(/_/g, '/');
    while (value.length % 4) {
      value += '=';
    }
    const binary = atob(value);
    const bytes = new Uint8Array(binary.length);
    for (let i = 0; i < binary.length; i++) {
      bytes[i] = binary.charCodeAt(i);
    }
    return bytes;
  }

  function concatBytes(parts) {
    const total = parts.reduce((sum, part) => sum + part.byteLength, 0);
    const out = new Uint8Array(total);
    let offset = 0;
    for (const part of parts) {
      out.set(part, offset);
      offset += part.byteLength;
    }
    return out;
  }

  function encodeUint24BE(value) {
    if (!Number.isInteger(value) || value < 0 || value > 0xffffff) {
      throw new Error('Encrypted JSON block is too large.');
    }
    return new Uint8Array([(value >>> 16) & 0xff, (value >>> 8) & 0xff, value & 0xff]);
  }

  function readUint24BE(bytes, offset) {
    if (offset + 3 > bytes.byteLength) {
      throw new Error('Invalid encrypted payload header.');
    }
    return (bytes[offset] << 16) | (bytes[offset + 1] << 8) | bytes[offset + 2];
  }

  async function deriveKeys(passphrase, saltBytes, iterations) {
    const baseKey = await crypto.subtle.importKey(
      'raw',
      utf8Encode(passphrase),
      'PBKDF2',
      false,
      ['deriveBits']
    );
    const bits = await crypto.subtle.deriveBits(
      {
        name: 'PBKDF2',
        salt: saltBytes,
        iterations,
        hash: 'SHA-256'
      },
      baseKey,
      512
    );
    const keyBytes = new Uint8Array(bits);
    const aesMaterial = keyBytes.slice(0, 32);
    const tokenMaterial = keyBytes.slice(32, 64);
    const aesKey = await crypto.subtle.importKey('raw', aesMaterial, { name: 'AES-GCM' }, false, ['encrypt', 'decrypt']);
    aesMaterial.fill(0);
    keyBytes.fill(0);
    return {
      aesKey,
      token: base64UrlEncode(tokenMaterial)
    };
  }

  async function encryptBytes(aesKey, bytes) {
    const iv = randomBytes(12);
    const encrypted = new Uint8Array(await crypto.subtle.encrypt({ name: 'AES-GCM', iv }, aesKey, bytes));
    return concatBytes([iv, encrypted]);
  }

  async function decryptBytes(aesKey, encryptedBytes) {
    if (!(encryptedBytes instanceof Uint8Array)) {
      encryptedBytes = new Uint8Array(encryptedBytes);
    }
    if (encryptedBytes.byteLength < 29) {
      throw new Error('Encrypted block is too small.');
    }
    const iv = encryptedBytes.slice(0, 12);
    const ciphertext = encryptedBytes.slice(12);
    return new Uint8Array(await crypto.subtle.decrypt({ name: 'AES-GCM', iv }, aesKey, ciphertext));
  }

  async function buildEncryptedPayload(message, files, aesKey, limits) {
    const maxFileSum = limits.maxFileSumBytes;
    const maxUpload = limits.maxUploadBytes;
    let originalTotal = 0;
    for (const file of files) {
      originalTotal += file.size;
    }
    if (originalTotal > maxFileSum) {
      throw new Error('Files are too large. The total original file size must be 15 MiB or less.');
    }

    const encryptedFiles = [];
    const fileMeta = [];
    for (const file of files) {
      const plain = new Uint8Array(await file.arrayBuffer());
      const encrypted = await encryptBytes(aesKey, plain);
      encryptedFiles.push(encrypted);
      fileMeta.push({
        name: file.name,
        bytelen: String(encrypted.byteLength)
      });
    }

    const json = JSON.stringify({
      message: String(message),
      files: fileMeta
    });
    const encryptedJson = await encryptBytes(aesKey, utf8Encode(json));
    if (encryptedJson.byteLength > 0xffffff) {
      throw new Error('Encrypted metadata block is too large.');
    }

    const header = encodeUint24BE(encryptedJson.byteLength);
    const assembled = concatBytes([header, encryptedJson, ...encryptedFiles]);
    const outer = await encryptBytes(aesKey, assembled);
    if (outer.byteLength > maxUpload) {
      throw new Error('Encrypted payload is larger than 16 MiB and cannot be stored.');
    }
    return { outer, jsonBytes: encryptedJson.byteLength, fileCount: encryptedFiles.length };
  }

  async function parseEncryptedPayload(outerBytes, aesKey) {
    const assembled = await decryptBytes(aesKey, outerBytes);
    if (assembled.byteLength < 3) {
      throw new Error('Invalid payload.');
    }
    const jsonLen = readUint24BE(assembled, 0);
    const jsonStart = 3;
    const jsonEnd = jsonStart + jsonLen;
    if (jsonLen <= 0 || jsonEnd > assembled.byteLength) {
      throw new Error('Invalid encrypted metadata length.');
    }
    const jsonBytes = assembled.slice(jsonStart, jsonEnd);
    const jsonPlain = await decryptBytes(aesKey, jsonBytes);
    const meta = JSON.parse(utf8Decode(jsonPlain));
    if (!meta || typeof meta.message !== 'string' || !Array.isArray(meta.files)) {
      throw new Error('Invalid metadata JSON.');
    }
    let offset = jsonEnd;
    const attachments = meta.files.map((file, index) => {
      const byteLen = Number(file.bytelen);
      if (!Number.isSafeInteger(byteLen) || byteLen < 29 || offset + byteLen > assembled.byteLength) {
        throw new Error('Invalid attachment length.');
      }
      const attachment = {
        index,
        name: String(file.name || `attachment-${index + 1}`),
        encryptedBytes: assembled.slice(offset, offset + byteLen),
        encryptedByteLength: byteLen
      };
      offset += byteLen;
      return attachment;
    });
    if (offset !== assembled.byteLength) {
      throw new Error('Encrypted payload has trailing bytes.');
    }
    return {
      message: meta.message,
      attachments
    };
  }

  async function decryptAttachment(attachment, aesKey) {
    return decryptBytes(aesKey, attachment.encryptedBytes);
  }

  window.TalkCrypto = {
    randomBytes,
    base64UrlEncode,
    base64UrlDecode,
    deriveKeys,
    buildEncryptedPayload,
    parseEncryptedPayload,
    decryptAttachment
  };
})();
