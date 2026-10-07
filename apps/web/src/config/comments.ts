export const commentConfig = {
  maxLength: Number(import.meta.env.VITE_COMMENT_MAX_LENGTH) || 2000,
  perPage: Number(import.meta.env.VITE_COMMENT_PER_PAGE) || 20,
  replyPerPage: Number(import.meta.env.VITE_COMMENT_REPLY_PER_PAGE) || 10,
}
