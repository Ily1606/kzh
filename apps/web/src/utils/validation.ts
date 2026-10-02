export function validateEmail(email: string): string {
  if (!email) return 'Email is required';
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) return 'Invalid email format';
  return '';
}

export function validatePassword(password: string): string {
  if (!password) return 'Password is required';
  if (password.length < 8) return 'Password must be at least 8 characters';
  return '';
}

export function isRequired(value: any, fieldName: string = 'This field'): string {
  if (!value) return `${fieldName} is required`;
  return '';
}

export function validateConfirmPassword(password: string, confirm: string): string {
  if (!confirm) return 'Please confirm your password';
  if (password !== confirm) return 'Passwords do not match';
  return '';
}
