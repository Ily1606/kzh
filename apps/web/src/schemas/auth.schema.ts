import { toTypedSchema } from "@vee-validate/zod";
import * as z from "zod";

// Shared reusable validation rules
export const emailRule = z
  .string()
  .min(1, "Email is required")
  .email("Invalid email address");

export const passwordRule = z
  .string()
  .min(8, "Password must be at least 8 characters");

export const confirmPasswordRule = z
  .string()
  .min(1, "Please confirm your password");

export const loginSchema = toTypedSchema(
  z.object({
    email: emailRule,
    password: z.string().min(1, "Password is required"),
  })
);

export const resetPasswordSchema = toTypedSchema(
  z.object({
    email: emailRule,
    password: passwordRule,
    password_confirmation: confirmPasswordRule,
  }).refine((data) => data.password === data.password_confirmation, {
    message: "Passwords do not match",
    path: ["password_confirmation"],
  })
);

export const forgotPasswordSchema = toTypedSchema(
  z.object({
    email: emailRule,
  })
);

export const registerSchema = toTypedSchema(
  z.object({
    name: z.string().min(1, "Full name is required"),
    email: emailRule,
    password: passwordRule,
    confirmPassword: confirmPasswordRule,
  }).refine((data) => data.password === data.confirmPassword, {
    message: "Passwords do not match",
    path: ["confirmPassword"],
  })
);


