import { describe, it, expect } from 'vitest';
import { isRequired, validateEmail, validatePassword, validateConfirmPassword } from '@/utils/validation';

describe('Validation Utility', () => {
  describe('isRequired', () => {
    it('returns error if value is empty', () => {
      expect(isRequired('')).toBe('Field is required');
      expect(isRequired('   ')).toBe('Field is required');
    });

    it('returns error with custom field name', () => {
      expect(isRequired('', 'Username')).toBe('Username is required');
    });

    it('returns empty string if value is valid', () => {
      expect(isRequired('test')).toBe('');
      expect(isRequired(' a ')).toBe('');
    });
  });

  describe('validateEmail', () => {
    it('returns error if email is empty', () => {
      expect(validateEmail('')).toBe('Email is required');
    });

    it('returns error if email format is invalid', () => {
      expect(validateEmail('invalid-email')).toBe('Invalid email format');
      expect(validateEmail('test@')).toBe('Invalid email format');
      expect(validateEmail('@example.com')).toBe('Invalid email format');
      expect(validateEmail('test@.com')).toBe('Invalid email format');
    });

    it('returns empty string if email is valid', () => {
      expect(validateEmail('test@example.com')).toBe('');
      expect(validateEmail('user.name+tag@domain.co.uk')).toBe('');
    });
  });

  describe('validatePassword', () => {
    it('returns error if password is empty', () => {
      expect(validatePassword('')).toBe('Password is required');
    });

    it('returns error if password is too short', () => {
      expect(validatePassword('1234567')).toBe('Password must be at least 8 characters');
    });

    it('returns empty string if password is valid', () => {
      expect(validatePassword('12345678')).toBe('');
      expect(validatePassword('strong-password!')).toBe('');
    });
  });

  describe('validateConfirmPassword', () => {
    it('returns error if confirm password is empty', () => {
      expect(validateConfirmPassword('password', '')).toBe('Confirm password is required');
    });

    it('returns error if passwords do not match', () => {
      expect(validateConfirmPassword('password123', 'password456')).toBe('Passwords do not match');
    });

    it('returns empty string if passwords match', () => {
      expect(validateConfirmPassword('password123', 'password123')).toBe('');
    });
  });
});
