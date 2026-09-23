import { describe, it, expect } from 'vitest';
import { cn } from '@/utils/cn';

describe('cn utility', () => {
  it('merges basic string classes', () => {
    expect(cn('class1', 'class2')).toBe('class1 class2');
  });

  it('handles conditional classes (object syntax)', () => {
    expect(cn({
      'class1': true,
      'class2': false,
      'class3': true
    })).toBe('class1 class3');
  });

  it('handles boolean evaluations in arrays', () => {
    const isTrue = true;
    const isFalse = false;
    expect(cn(
      'base-class',
      isTrue && 'active',
      isFalse && 'hidden'
    )).toBe('base-class active');
  });

  it('resolves tailwind class conflicts correctly', () => {
    // tailwind-merge should resolve conflict by picking the last one
    expect(cn('px-2 py-1', 'px-4')).toBe('py-1 px-4');
    expect(cn('bg-red-500', 'bg-blue-500')).toBe('bg-blue-500');
  });

  it('handles nested arrays and complex combinations', () => {
    expect(cn(
      'class1',
      ['class2', 'class3'],
      { 'class4': true, 'class5': false },
      'p-2 p-4' // inner conflict
    )).toBe('class1 class2 class3 class4 p-4');
  });
});

