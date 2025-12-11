export const inputMaskingConfiguration = {
  default: {
    get: (modelValue?: string | number) => modelValue,
    set: (value?: string | number) => value,
  },
  number: {
    get: (modelValue?: string | number) =>
      modelValue?.toString().replace(/\D/g, ""),
    set: (value?: string | number) => value?.toString().replace(/\D/g, ""),
  },
  npwp: {
    get: (modelValue?: string | number) => {
      const masked = modelValue
        ?.toString()
        .replace(
          /^(\d{0,2})(\d{0,3})(\d{0,3})(\d{0,1})(\d{0,3})(\d{0,4})$/,
          (_match, p1, p2, p3, p4, p5, p6) => {
            let result = p1;
            if (p2) result += "." + p2;
            if (p3) result += "." + p3;
            if (p4) result += "." + p4;
            if (p5) result += "-" + p5;
            if (p6) result += "." + p6;
            return result;
          }
        );

      return masked;
    },
    set: (value?: string | number) => value?.toString().replace(/\D/g, ""),
  },
  numbering: {
    get: (modelValue?: string | number) =>
      modelValue ? Number(modelValue).toLocaleString("id-ID") : undefined,
    set: (value?: string | number) =>
      value ? Number(value.toString().replace(/\D/g, "")) : undefined,
  },
  url: {
    get: (modelValue?: string | number) => modelValue,
    set: (value?: string | number) => {
      let url = value?.toString().replace(/ /g, "") || "";
      url = url.replace(/^https?:\/\//, ""); // Remove any existing http(s)://
      return url ? "https://" + url : "";
    },
  },
  urlHost: {
    get: (modelValue?: string | number) => modelValue,
    set: (value?: string | number) => {
      const url = value?.toString().replace(/ /g, "") || "";
      // replace url path like https:// chrome:// http://
      return url.replace(/^https?:\/\//, "");
    },
  },
  waOfferCode: {
    get: (modelValue?: string | number) =>
      modelValue?.toString().replace(/[^a-zA-Z0-9]/g, ""),
    set: (value?: string | number) =>
      value?.toString().replace(/[^a-zA-Z0-9]/g, ""),
  },
  waButtonLabel: {
    get: (modelValue?: string | number) =>
      modelValue?.toString().replace(/[^a-zA-Z0-9 .,:_\-/'&+!?()]/g, ""),
    set: (value?: string | number) =>
      value?.toString().replace(/[^a-zA-Z0-9 .,:_\-/'&+!?()]/g, ""),
  },
};

export const BASE_CLASS = [
  "w-full px-2.5 py-2 text-sm rounded-lg border border-outline-variant ring-inset placeholder:text-flux-outline focus-visible:outline-none ring-0 focus-visible:ring-offset-1 focus-visible:ring-offset-flux-primary disabled:cursor-not-allowed disabled:opacity-50 peer focus-visible:text-on-surface focus-visible:bg-surface-container-lowest read-only:bg-surface-container-low autofill:bg-background",
  "text-onSurface ring-offset-0 ring-offset-flux-primary focus:border-flux-primary bg-background dark:bg-surface-container-high",
];

export const BASE_INVALID_CLASS =
  "text-flux-error ring-offset-1 border-flux-error ring-offset-flux-error focus:border-flux-error bg-surface-container-low";
