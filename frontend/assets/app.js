(function () {
  'use strict';

  const cfg = window.TALK_CONFIG || {};
  const state = {
    meta: null,
    code: null
  };

  function $(id) {
    return document.getElementById(id);
  }

  function apiBase() {
    const base = (cfg.API_BASE || '').trim();
    if (base) return base.replace(/\/+$/, '');
    return `${window.location.origin}/api`;
  }

  function frontendBase() {
    const configured = (cfg.FRONTEND_URL || '').trim();
    if (configured) return configured.replace(/\/+$/, '/') ;
    const path = window.location.pathname;
    if (path && !path.includes('.') && path !== '/') {
      return `${window.location.origin}/`;
    }
    return `${window.location.origin}${window.location.pathname.replace(/[^/]*$/, '')}`;
  }

  function messageLink(code) {
    return new URL(code, frontendBase()).toString();
  }

  function extractCode() {
    const params = new URLSearchParams(window.location.search);
    const fromQuery = params.get('code');
    if (fromQuery) return fromQuery.trim();

    const hash = window.location.hash.replace(/^#\/?/, '').trim();
    if (hash && /^[A-Za-z0-9_-]{8,24}$/.test(hash)) return hash;

    const path = window.location.pathname.replace(/^\/+|\/+$/g, '');
    if (path && !path.includes('/') && !path.includes('.') && /^[A-Za-z0-9_-]{8,24}$/.test(path)) {
      return path;
    }
    return '';
  }

  function show(id) {
    for (const panel of document.querySelectorAll('[data-panel]')) {
      panel.hidden = panel.id !== id;
    }
  }

  function setStatus(id, text, kind) {
    const el = $(id);
    if (!el) return;
    el.textContent = text || '';
    el.className = kind ? `status ${kind}` : 'status';
  }

  async function apiFetch(path, options) {
    const response = await fetch(`${apiBase()}${path}`, {
      ...options,
      headers: {
        'Content-Type': 'application/json',
        ...(options && options.headers ? options.headers : {})
      }
    });
    let body = null;
    try {
      body = await response.json();
    } catch (error) {
      throw new Error('Backend returned a non-JSON response.');
    }
    if (!response.ok || !body.success) {
      throw new Error((body && body.error) || `HTTP ${response.status}`);
    }
    return body;
  }

  function setBusy(button, busy, busyText) {
    if (!button) return;
    if (busy) {
      button.dataset.originalText = button.textContent;
      button.textContent = busyText || 'Please wait...';
      button.disabled = true;
    } else {
      button.textContent = button.dataset.originalText || button.textContent;
      button.disabled = false;
    }
  }

  async function createMessage(oneTime) {
    const button = oneTime ? $('create-once') : $('create-unlimited');
    const message = $('message').value;
    const passphrase = $('passphrase').value;
    const hint = $('hint').value;
    const expireDays = Math.max(1, parseInt($('expire-days').value, 10) || (cfg.DEFAULT_EXPIRE_DAYS || 5));
    const iterations = parseInt(cfg.PBKDF2_ITERATIONS, 10) || 310000;

    if (!message.trim()) {
      setStatus('create-status', 'Please enter a message.', 'error');
      return;
    }
    if (!passphrase) {
      setStatus('create-status', 'Please enter a passphrase.', 'error');
      return;
    }

    setBusy(button, true, 'Encrypting...');
    setStatus('create-status', 'Encrypting locally in this browser...', 'info');

    try {
      const encrypted = await window.TalkCrypto.encryptMessage(message, passphrase, iterations);
      setStatus('create-status', 'Uploading encrypted message...', 'info');
      const result = await apiFetch('/create.php', {
        method: 'POST',
        body: JSON.stringify({
          ...encrypted,
          hint,
          oneTime,
          expireDays
        })
      });

      const link = messageLink(result.code);
      $('generated-link').value = link;
      $('generated-code').textContent = result.code;
      $('generated-expiry').textContent = `${result.expiresInDays} day(s)`;
      $('result-box').hidden = false;
      setStatus('create-status', 'Done. Send the link and share the passphrase through a separate channel.', 'success');
    } catch (error) {
      setStatus('create-status', error.message || 'Failed to create message.', 'error');
    } finally {
      setBusy(button, false);
    }
  }

  async function loadMeta(code) {
    show('open-panel');
    state.code = code;
    $('open-code').textContent = code;
    setStatus('open-status', 'Loading message metadata...', 'info');

    try {
      const body = await apiFetch(`/meta.php?code=${encodeURIComponent(code)}`, { method: 'GET' });
      state.meta = body.message;
      $('open-hint').textContent = body.message.hint || 'No hint was provided.';
      $('open-type').textContent = body.message.oneTime ? 'One-time message' : 'Unlimited-access message';
      $('open-expiry').textContent = body.message.expiresAt || '';
      setStatus('open-status', 'Enter the passphrase to decrypt this message locally.', 'success');
    } catch (error) {
      $('open-form').hidden = true;
      setStatus('open-status', error.message || 'Message not found or expired.', 'error');
    }
  }

  async function openMessage() {
    const button = $('open-button');
    const passphrase = $('open-passphrase').value;
    if (!passphrase) {
      setStatus('open-status', 'Please enter the passphrase.', 'error');
      return;
    }
    if (!state.meta || !state.code) {
      setStatus('open-status', 'Message metadata is not loaded.', 'error');
      return;
    }

    setBusy(button, true, 'Checking...');
    setStatus('open-status', 'Deriving access token locally...', 'info');

    try {
      const accessToken = await window.TalkCrypto.accessTokenFor(passphrase, state.meta.salt, state.meta.iterations);
      const body = await apiFetch('/open.php', {
        method: 'POST',
        body: JSON.stringify({ code: state.code, accessToken })
      });
      setStatus('open-status', 'Decrypting locally in this browser...', 'info');
      const plaintext = await window.TalkCrypto.decryptMessage(
        body.message.ciphertext,
        body.message.iv,
        body.message.salt,
        passphrase,
        body.message.iterations
      );
      $('message-output').textContent = plaintext;
      $('message-output-box').hidden = false;
      $('open-form').hidden = true;
      setStatus('open-status', body.message.oneTime ? 'Message decrypted. The server-side copy has been deleted.' : 'Message decrypted.', 'success');
    } catch (error) {
      setStatus('open-status', error.message || 'Wrong passphrase or message cannot be opened.', 'error');
    } finally {
      setBusy(button, false);
    }
  }

  async function copyGeneratedLink() {
    try {
      await navigator.clipboard.writeText($('generated-link').value);
      setStatus('create-status', 'Link copied.', 'success');
    } catch (error) {
      setStatus('create-status', 'Copy failed. Please copy the link manually.', 'error');
    }
  }

  function init() {
    $('create-once').addEventListener('click', () => createMessage(true));
    $('create-unlimited').addEventListener('click', () => createMessage(false));
    $('copy-link').addEventListener('click', copyGeneratedLink);
    $('open-button').addEventListener('click', openMessage);
    $('new-message').addEventListener('click', () => {
      history.pushState(null, '', frontendBase());
      show('create-panel');
    });

    const code = extractCode();
    if (code) {
      loadMeta(code);
    } else {
      show('create-panel');
    }
  }

  document.addEventListener('DOMContentLoaded', init);
}());
