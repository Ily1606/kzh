import { toTypedSchema } from "@vee-validate/zod";
import * as z from "zod";

export const loginSchema = toTypedSchema(
  z.object({
    email: z.string().email("Invalid email address"),
    password: z.string().min(8, "Password must be at least 8 characters"),
  })
);

export const resetPasswordSchema = toTypedSchema(
  z.object({
    email: z.string().min(1, "Email is required").email("Invalid email address"),
    password: z.string().min(8, "Password must be at least 8 characters"),
    password_confirmation: z.string().min(1, "Please confirm your password"),
  }).refine((data) => data.password === data.password_confirmation, {
    message: "Passwords do not match",
    path: ["password_confirmation"],
  })
);

export const forgotPasswordSchema = toTypedSchema(
  z.object({
    email: z.string().min(1, "Email is required").email("Invalid email address"),
  })
);

