(function () {
  'use strict';

  const config = window.TALK_CONFIG || {};
  const cryptoHelpers = window.TalkCrypto;

  const state = {
    code: '',
    meta: null,
    aesKey: null,
    openedPayload: null
  };

  function $(id) {
    return document.getElementById(id);
  }

  function show(element, visible) {
    element.hidden = !visible;
  }

  function setStatus(id, text, kind) {
    const el = $(id);
    el.textContent = text || '';
    el.className = 'status ' + (kind || '');
  }

  function apiUrl(path) {
    return String(config.API_BASE || '').replace(/\/+$/, '') + '/' + path.replace(/^\/+/, '');
  }

  function frontendUrl(code) {
    const base = String(config.FRONTEND_BASE || window.location.origin).replace(/\/+$/, '');
    return base + '/' + encodeURIComponent(code);
  }

  function effectivePassphrase(value) {
    return value === '' ? String(config.DEFAULT_PASSWORD || '') : value;
  }

  function getCodeFromLocation() {
    const params = new URLSearchParams(window.location.search);
    const queryCode = params.get('code');
    if (queryCode) {
      return queryCode.trim();
    }
    const hash = window.location.hash.replace(/^#\/?/, '');
    if (hash && !hash.includes('=')) {
      return hash.trim();
    }
    const path = window.location.pathname.replace(/^\/+|\/+$/g, '');
    if (path && !path.includes('/')) {
      return path.trim();
    }
    if (path.startsWith('open/')) {
      return path.slice(5).trim();
    }
    return '';
  }

  async function fetchJson(url, options) {
    const response = await fetch(url, options);
    let data = null;
    try {
      data = await response.json();
    } catch (e) {
      throw new Error('Server returned a non-JSON response.');
    }
    if (!response.ok || !data.ok) {
      throw new Error(data && data.error ? data.error : 'Request failed.');
    }
    return data;
  }

  function postBlobWithXhr(url, body) {
    return new Promise((resolve, reject) => {
      const xhr = new XMLHttpRequest();
      xhr.open('POST', url, true);
      xhr.responseType = 'arraybuffer';
      xhr.setRequestHeader('Content-Type', 'application/json');
      xhr.onload = function () {
        if (xhr.status >= 200 && xhr.status < 300) {
          resolve(new Uint8Array(xhr.response));
          return;
        }
        const text = xhr.response ? new TextDecoder().decode(new Uint8Array(xhr.response)) : '';
        try {
          const err = JSON.parse(text);
          reject(new Error(err.error || 'Failed to fetch encrypted blob.'));
        } catch (e) {
          reject(new Error('Failed to fetch encrypted blob.'));
        }
      };
      xhr.onerror = function () {
        reject(new Error('Network error while fetching encrypted blob.'));
      };
      xhr.send(JSON.stringify(body));
    });
  }

  async function loadMeta(code) {
    state.code = code;
    state.meta = null;
    setStatus('open-status', 'Loading message metadata...', 'info');
    const data = await fetchJson(apiUrl('meta.php?code=' + encodeURIComponent(code)), { method: 'GET' });
    state.meta = data;
    $('open-code').value = data.code;
    $('hint-text').textContent = data.hint || '(No hint provided)';
    $('expires-text').textContent = data.expires_at || '';
    show($('meta-panel'), true);
    show($('decrypt-panel'), true);
    setStatus('open-status', 'Metadata loaded. Enter the passphrase to open it.', 'success');
  }

  async function handleCreate(event) {
    event.preventDefault();
    setStatus('create-status', '', '');
    show($('created-panel'), false);

    const message = $('message').value;
    const passphrase = effectivePassphrase($('create-passphrase').value);
    const hint = $('hint').value;
    const expiresMins = $('expires-mins').value;
    const multiView = $('multi-view').checked;
    const files = Array.from($('files').files || []);

    if (!message && files.length === 0) {
      setStatus('create-status', 'Enter a message or attach at least one file.', 'error');
      return;
    }

    try {
      setStatus('create-status', 'Encrypting message and attachments in your browser...', 'info');
      const saltBytes = cryptoHelpers.randomBytes(16);
      const iterations = Number(config.PBKDF2_ITERATIONS || 210000);
      const derived = await cryptoHelpers.deriveKeys(passphrase, saltBytes, iterations);
      const built = await cryptoHelpers.buildEncryptedPayload(message, files, derived.aesKey, {
        maxFileSumBytes: Number(config.MAX_FILE_SUM_BYTES || 15 * 1024 * 1024),
        maxUploadBytes: Number(config.MAX_UPLOAD_BYTES || 16 * 1024 * 1024 - 1)
      });

      setStatus('create-status', 'Uploading encrypted blob...', 'info');
      const form = new FormData();
      form.append('salt', cryptoHelpers.base64UrlEncode(saltBytes));
      form.append('token', derived.token);
      form.append('hint', hint);
      form.append('expires_mins', expiresMins);
      form.append('iterations', String(iterations));
      form.append('kdf', 'PBKDF2-SHA256');
      form.append('multi_view', multiView ? '1' : '0');
      form.append('ciphertext', new Blob([built.outer], { type: 'application/octet-stream' }), 'payload.bin');

      const data = await fetchJson(apiUrl('create.php'), {
        method: 'POST',
        body: form
      });
      const link = frontendUrl(data.code);
      $('created-link').textContent = link;
      $('created-link').href = link;
      $('created-detail').textContent = `Encrypted size: ${data.ciphertext_bytes} bytes. Files: ${built.fileCount}. Multiple views: ${multiView ? 'yes' : 'no'}.`;
      show($('created-panel'), true);
      setStatus('create-status', 'Created successfully.', 'success');
    } catch (error) {
      setStatus('create-status', error.message || String(error), 'error');
    }
  }

  async function handleLoadMeta(event) {
    event.preventDefault();
    const code = $('open-code').value.trim();
    if (!code) {
      setStatus('open-status', 'Enter a message code.', 'error');
      return;
    }
    try {
      await loadMeta(code);
    } catch (error) {
      setStatus('open-status', error.message || String(error), 'error');
    }
  }

  async function handleOpen(event) {
    event.preventDefault();
    setStatus('open-status', '', '');
    $('message-output').innerHTML = '';
    $('attachments-list').textContent = '';
    show($('result-panel'), false);
    show($('attachments-panel'), false);

    if (!state.meta) {
      setStatus('open-status', 'Load the message metadata first.', 'error');
      return;
    }
    const passphrase = effectivePassphrase($('open-passphrase').value);

    try {
      setStatus('open-status', 'Checking passphrase...', 'info');
      const saltBytes = cryptoHelpers.base64UrlDecode(state.meta.salt);
      const derived = await cryptoHelpers.deriveKeys(passphrase, saltBytes, Number(state.meta.iterations));
      await fetchJson(apiUrl('check.php'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ code: state.meta.code, token: derived.token })
      });

      setStatus('open-status', 'Fetching encrypted blob...', 'info');
      const outerBytes = await postBlobWithXhr(apiUrl('open.php'), { code: state.meta.code, token: derived.token });
      const payload = await cryptoHelpers.parseEncryptedPayload(outerBytes, derived.aesKey);
      state.aesKey = derived.aesKey;
      state.openedPayload = payload;

      $('message-output').innerHTML = '<pre>' + payload.message + '</pre>';
      renderAttachments(payload.attachments);
      show($('result-panel'), true);
      const openedStatus = state.meta.multi_view ? 'Opened successfully. This message can be viewed again until it expires.' : 'Opened successfully. The server-side copy has been deleted.';
      setStatus('open-status', openedStatus, 'success');
    } catch (error) {
      setStatus('open-status', error.message || String(error), 'error');
    }
  }

  function renderAttachments(attachments) {
    const list = $('attachments-list');
    list.textContent = '';
    if (!attachments.length) {
      show($('attachments-panel'), false);
      return;
    }
    for (const attachment of attachments) {
      const item = document.createElement('li');
      const link = document.createElement('a');
      link.href = '#';
      link.textContent = attachment.name;
      link.addEventListener('click', async function (event) {
        event.preventDefault();
        await downloadAttachment(attachment);
      });
      const size = document.createElement('span');
      size.className = 'attachment-size';
      size.textContent = ` encrypted ${attachment.encryptedByteLength} bytes`;
      item.appendChild(link);
      item.appendChild(size);
      list.appendChild(item);
    }
    show($('attachments-panel'), true);
  }

  async function downloadAttachment(attachment) {
    try {
      setStatus('open-status', `Decrypting ${attachment.name}...`, 'info');
      const plain = await cryptoHelpers.decryptAttachment(attachment, state.aesKey);
      const blob = new Blob([plain], { type: 'application/octet-stream' });
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = attachment.name || 'attachment.bin';
      document.body.appendChild(a);
      a.click();
      a.remove();
      setTimeout(() => URL.revokeObjectURL(url), 60000);
      setStatus('open-status', 'Attachment decrypted. Your browser should start the download.', 'success');
    } catch (error) {
      setStatus('open-status', error.message || String(error), 'error');
    }
  }

  function copyCreatedLink() {
    const link = $('created-link').textContent;
    navigator.clipboard.writeText(link).then(
      () => setStatus('create-status', 'Link copied.', 'success'),
      () => setStatus('create-status', 'Copy failed. Please copy the link manually.', 'error')
    );
  }

  function initialize() {
    $('create-form').addEventListener('submit', handleCreate);
    $('load-meta-form').addEventListener('submit', handleLoadMeta);
    $('decrypt-form').addEventListener('submit', handleOpen);
    $('copy-link').addEventListener('click', copyCreatedLink);

    const code = getCodeFromLocation();
    if (code) {
      $('open-code').value = code;
      loadMeta(code).catch((error) => setStatus('open-status', error.message || String(error), 'error'));
      $('open-card').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  }

  document.addEventListener('DOMContentLoaded', initialize);
})();
