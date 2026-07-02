export type KiriofRuntimeValue = any;

export type KiriofStoreApiAddress = Record<string, KiriofRuntimeValue> & {
  email?: string;
};

export type KiriofStoreApiPayload = {
  additional_fields: Record<string, string>;
  shipping_address?: KiriofStoreApiAddress;
  billing_address?: KiriofStoreApiAddress;
};
