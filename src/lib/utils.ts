import { clsx, type ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';
export type { WithElementRef, WithoutChildren, WithoutChildrenOrChild } from 'svelte-toolbelt';

export function cn(...inputs: ClassValue[]): string {
  return twMerge(clsx(inputs));
}
