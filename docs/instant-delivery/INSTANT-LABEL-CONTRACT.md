# Instant label contract investigation

## Verified published evidence

Read-only investigation of the current official [OpenAPI JSON](https://developer.kiriminaja.com/docs/openapi/json) (version `6.2.0`) found **no label or print operation** in its `paths`. Its introductory Shipping Label section describes required content, including Code 128A AWB barcodes, but does not establish an Instant label endpoint or an HTML/PDF response contract.

The bundled official `kiriminaja/kiriminaja-php` SDK provides `KiriminAja::printAWB(array $data)`; `src/Repositories/AWBRepository.php` posts to `api/mitra/v6.1/awb/print`. Its README demonstrates `['awb' => ['AWB123', 'AWB456']]`. The current [upstream repository source](https://raw.githubusercontent.com/kiriminaja/php/master/src/Repositories/AWBRepository.php) agrees. None of these sources explicitly promises GoSend/Grab Instant support. The suggested `/api/mitra/v3/label` endpoint is absent from both the current OpenAPI and bundled SDK. The plugin's Express `getPrintAwb()` uses `/api/mitra/v6.1/awb/print` with legacy payload retries; these retries do not prove Instant support.

## Safe implementation boundary

Until KiriminAja confirms the Instant print contract, Instant printing does **not** call the Express print API, and never calls any booking endpoint. No live API, booking, payment, or shipment was submitted during this investigation.

The authenticated Instant AJAX preview retains its same-origin, separately nonced `admin-post.php?action=kiriof_instant_labels` HTML route. Metadata explicitly reports `provider: local`, `carrier_available: false`, and `fallback_reason`; the UI should present this reason and must not describe the document as carrier-issued. The local template already carries that disclaimer and prints actual persisted AWB text only, not a fabricated barcode.

Every batch is validated in full before output: exact transaction identity, Instant partition, supported booked courier/state, eligible WooCommerce order, immutable booked address/item snapshots, syntactically valid persisted AWB, and no duplicate AWB belonging to different requested transactions. Authorization and both AJAX/render nonces remain mandatory. Mixed Express/Instant batches are rejected by this service; frontend partitioning must use separate providers.

## Release gate for carrier-first printing

Obtain explicit confirmation of the endpoint, whether it accepts Instant AWB or KiriminAja order ID, account permissions, response type/identity, and official render URL/asset hosts. Then implement an authenticated carrier-first adapter with whole-batch identity checks and an honestly marked local fallback. Do not inject untrusted remote HTML into the admin origin or accept arbitrary response URLs. The present evidence is insufficient to safely enable that path.
