// Keep filenames aligned with CourierLogoAssets.php. Artwork is packaged once,
// outside Vite's asset pipeline, for both Classic checkout and the admin UI.
export const courierFiles: Record<string, string> = {
  anteraja: 'anteraja.png',
  borzo: 'borzo.png',
  gosend: 'gosend.png',
  grab_express: 'grab-express.png',
  idx: 'id-express.png',
  idexpress: 'id-express.png',
  id_express: 'id-express.png',
  jne: 'jne.png',
  jnt: 'jnt.png',
  jntcargo: 'jnt-cargo.png',
  jnt_cargo: 'jnt-cargo.png',
  jtcargo: 'jnt-cargo.png',
  lalamove: 'lalamove.png',
  lion: 'lion.png',
  lionparcel: 'lion.png',
  ncs: 'ncs.png',
  ninja: 'ninja.png',
  ninja_inter: 'ninja-inter.png',
  paxel: 'paxel.png',
  pos: 'pos.png',
  posindonesia: 'pos.png',
  rpx: 'rpx.png',
  sap: 'sap.png',
  sapx: 'sap.png',
  sentral: 'sentral.png',
  sentral_cargo: 'sentral.png',
  shopee_express: 'shopee-express.png',
  sicepat: 'sicepat.png',
  spx: 'shopee-express.png',
  tiki: 'tiki.png',
};

export function courierAssetUrl(file: string, moduleUrl = import.meta.url): string {
  // Vite puts shared chunks in dist/assets and entry modules directly in dist.
  // Keep the base dynamic so Vite does not copy/rewrite the shared PNG artwork.
  const runtimeBase = moduleUrl;
  const relativeRoot = new URL(runtimeBase).pathname.includes('/assets/admin/dist/assets/')
    ? '../../../buyer/img/couriers/'
    : '../../buyer/img/couriers/';
  return new URL(relativeRoot + file, runtimeBase).href;
}

export const courierImages: Record<string, string> = Object.fromEntries(
  Object.entries(courierFiles).map(([code, file]) => [code, courierAssetUrl(file)]),
);

const courierAliases: Record<string, string> = {
  'grab express': 'grab_express',
  'id express': 'idx',
  'j&t': 'jnt',
  'j&t cargo': 'jntcargo',
  'j&t express': 'jnt',
  'lion parcel': 'lion',
  'ninja international': 'ninja_inter',
  'ninja inter': 'ninja_inter',
  'ninja xpress': 'ninja',
  'pos indonesia': 'posindonesia',
  'sap express': 'sap',
  'sentral cargo': 'sentral',
  'shopee express': 'spx',
};

function normalizeCourierKey(value: string): string {
  return value
    .trim()
    .toLowerCase()
    .replace(/[._-]+/g, ' ')
    .replace(/\s+/g, ' ');
}

export function courierImage(code: string, service = ''): string | undefined {
  const candidates = [code, service];

  for (const candidate of candidates) {
    const directKey = candidate.trim().toLowerCase();
    if (courierImages[directKey]) return courierImages[directKey];

    const normalized = normalizeCourierKey(candidate);
    const mapped = courierAliases[normalized] ?? normalized.replace(/[^a-z0-9]+/g, '');
    if (courierImages[mapped]) return courierImages[mapped];
  }

  return undefined;
}
