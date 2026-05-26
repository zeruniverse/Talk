window.TALK_CONFIG = {
  // Change this to your Aliyun FC backend API URL.
  // Example: https://api.example.com/api
  API_BASE: 'https://api.example.com/api',

  // Change this to your Cloudflare Pages frontend URL.
  // Example: https://talk.example.com
  FRONTEND_BASE: 'https://talk.example.com',

  // Used when the create/open passphrase field is left blank.
  DEFAULT_PASSWORD: 'change-this-default-passphrase',

  // Must match the backend value used for new messages.
  PBKDF2_ITERATIONS: 2333333,

  // 15 MiB original total file size limit.
  MAX_FILE_SUM_BYTES: 15 * 1024 * 1024,

  // Encrypted blob must fit MySQL MEDIUMBLOB.
  MAX_UPLOAD_BYTES: 16 * 1024 * 1024 - 1
};
