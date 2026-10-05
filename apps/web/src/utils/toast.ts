import { toast } from "vue-sonner";

export const ERROR_DURATION = 8000;
export const SUCCESS_DURATION = 4000;

export function notifyError(message: string) {
  /*
    `toast.dismiss()` trước khi toast mới — tránh các toast chồng lên nhau khi người dùng bấm submit nhiều lần liên tiếp.
  */
  toast.dismiss();
  toast.error(message, { duration: ERROR_DURATION });
}

export function notifySuccess(message: string) {
  /*
    `toast.dismiss()` trước khi toast mới — giúp tránh các toast chồng lên nhau khi người dùng bấm submit nhiều lần liên tiếp.
  */
  toast.dismiss();
  toast.success(message, { duration: SUCCESS_DURATION });
}
