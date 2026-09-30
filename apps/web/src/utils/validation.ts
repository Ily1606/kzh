export function isRequired(value: string, fieldName: string = "Field"): string {
  if (!value || value.trim().length === 0) return `${fieldName} is required`;
  return "";
}

export function validateEmail(val: string): string {
  const req = isRequired(val, "Email");
  if (req) return req;
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val)) return "Invalid email format";
  return "";
}

export function validatePassword(val: string): string {
  const req = isRequired(val, "Password");
    if (req) return req;
    if (val.length < 8) return "Password must be at least 8 characters";
  return "";
}

export function validateConfirmPassword(password: string, confirmPassword: string): string {
  const req = isRequired(confirmPassword, "Confirm password");
  if (req) return req;
  if (password !== confirmPassword) return "Passwords do not match";
  return "";
}
