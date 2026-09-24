import anteraja from '../../assets/images/kiriminaja-kurir/Anteraja.png';
import borzo from '../../assets/images/kiriminaja-kurir/Borzo.png';
import gosend from '../../assets/images/kiriminaja-kurir/Gosend.png';
import grabExpress from '../../assets/images/kiriminaja-kurir/grab_express.png';
import idExpress from '../../assets/images/kiriminaja-kurir/ID Express.png';
import jne from '../../assets/images/kiriminaja-kurir/JNE.png';
import jntCargo from '../../assets/images/kiriminaja-kurir/J&T Cargo.png';
import jntExpress from '../../assets/images/kiriminaja-kurir/J&T Express.png';
import lalamove from '../../assets/images/kiriminaja-kurir/lalamove.png';
import lionParcel from '../../assets/images/kiriminaja-kurir/Lion Parcel.png';
import ncs from '../../assets/images/kiriminaja-kurir/NCS.png';
import ninja from '../../assets/images/kiriminaja-kurir/Ninja.png';
import ninjaInter from '../../assets/images/kiriminaja-kurir/ninja_inter.png';
import paxel from '../../assets/images/kiriminaja-kurir/Paxel.png';
import posIndonesia from '../../assets/images/kiriminaja-kurir/POS IND.png';
import rpx from '../../assets/images/kiriminaja-kurir/RPX.png';
import sap from '../../assets/images/kiriminaja-kurir/SAPX.png';
import sentralCargo from '../../assets/images/kiriminaja-kurir/Sentral Cargo.png';
import shopeeExpress from '../../assets/images/kiriminaja-kurir/shopee-express.png';
import sicepat from '../../assets/images/kiriminaja-kurir/Sicepat.png';
import spx from '../../assets/images/kiriminaja-kurir/SPX.png';
import tiki from '../../assets/images/kiriminaja-kurir/TIKI.png';

export const courierImages: Record<string, string> = {
  anteraja,
  borzo,
  gosend,
  grab_express: grabExpress,
  idx: idExpress,
  idexpress: idExpress,
  jne,
  jnt: jntExpress,
  jntcargo: jntCargo,
  lalamove,
  lion: lionParcel,
  ncs,
  ninja,
  ninja_inter: ninjaInter,
  paxel,
  posindonesia: posIndonesia,
  rpx,
  sap,
  sentral: sentralCargo,
  shopee_express: shopeeExpress,
  sicepat,
  spx,
  tiki,
};

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
