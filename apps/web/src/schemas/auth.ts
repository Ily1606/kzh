import * as z from "zod";

export const email = z.string().min(1, "Email is required").email("Invalid email address");
export const newPassword = z.string().min(8, "Password must be at least 8 characters");

