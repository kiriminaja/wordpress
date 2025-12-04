import { type ClassValue, clsx } from "clsx";
import { twMerge } from "tailwind-merge";

/**
 * It helps you to avoid a tailwind class duplications, will use one instead using both
 */
export const cn = (...inputs: ClassValue[]) => {
  return twMerge(clsx(inputs));
};
