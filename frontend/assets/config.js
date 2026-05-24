/*
 * Talk frontend configuration.
 *
 * This file is intentionally local and editable. Do not load configuration from
 * a third-party script. Deploy frontend/ to Cloudflare Pages and set API_BASE
 * to your Aliyun FC backend API URL.
 */
window.TALK_CONFIG = {
  // Example: 'https://api.example.com/api'
  API_BASE: '',

  // Example: 'https://talk.example.com/'
  // Leave empty to use the current site origin and path.
  FRONTEND_URL: '',

  // Must fit backend MIN_PBKDF2_ITERATIONS and MAX_PBKDF2_ITERATIONS.
  PBKDF2_ITERATIONS: 310000,

  DEFAULT_EXPIRE_DAYS: 5
};
