<script lang="ts">
    import { onMount } from "svelte";
    import {
        IconBox,
        IconCheck,
        IconCircleCheck,
        IconExternalLink,
        IconMapPin,
        IconPackage,
        IconPhone,
        IconPrinter,
        IconRefresh,
        IconRoute,
        IconTruck,
        IconUser,
        IconX,
    } from "@tabler/icons-svelte";
    import { Button } from "$lib/components/ui/button";
    import * as Card from "$lib/components/ui/card";
    import StatusBadge from "$lib/admin-list/StatusBadge.svelte";
    import KiriofCard from "$lib/ui/KiriofCard.svelte";
    import CopyableValue from "$lib/ui/CopyableValue.svelte";
    import PrintPreviewDialog from "$lib/ui/PrintPreviewDialog.svelte";
    import Toolbar from "$lib/ui/Toolbar.svelte";
    import { courierImage } from "$lib/transactions/courier-images";
    import TransactionActionDialogs, {
        type TransactionActionDialog,
    } from "$lib/transactions/TransactionActionDialogs.svelte";
    import type { TrackingResponse, TransactionDetailBootstrap } from "./types";

    let {
        bootstrap,
        onNavigate,
        openAdjustDeficit = false,
    }: {
        bootstrap: TransactionDetailBootstrap;
        onNavigate?: (href: string | URL) => Promise<void> | void;
        openAdjustDeficit?: boolean;
    } = $props();
    let transaction = $derived(bootstrap.transaction);
    let i18n = $derived(bootstrap.i18n);
    let tracking = $state<TrackingResponse | null>(null);
    let trackingError = $state("");
    let loadingTracking = $state(false);
    let actionDialog = $state<TransactionActionDialog | null>(null);
    let printPreviewOpen = $state(false);

    function finishAction(): void {
        if (onNavigate) {
            void onNavigate(window.location.href);
            return;
        }
        window.location.reload();
    }

    function currency(amount: number): string {
        return `Rp${new Intl.NumberFormat("id-ID", { maximumFractionDigits: 0 }).format(amount)}`;
    }

    function formatPhone(phone: string): string {
        const digits = phone.replace(/\D/g, "");
        if (!digits) return phone;
        if (digits.startsWith("62")) return `+${digits}`;
        if (digits.startsWith("0")) return `+62${digits.slice(1)}`;
        return `+${digits}`;
    }

    async function loadTracking(): Promise<void> {
        if (
            !transaction.supportsLiveTracking ||
            !transaction.shipment.trackingOrder ||
            loadingTracking
        )
            return;
        loadingTracking = true;
        trackingError = "";
        const body = new URLSearchParams({
            action: "kiriof_transaction_detail_tracking",
            nonce: bootstrap.ajax.nonce,
            order_id: transaction.shipment.trackingOrder,
        });

        try {
            const response = await fetch(bootstrap.ajax.url, {
                method: "POST",
                credentials: "same-origin",
                headers: {
                    "Content-Type":
                        "application/x-www-form-urlencoded; charset=UTF-8",
                },
                body,
            });
            const payload = (await response.json()) as {
                success?: boolean;
                data?: TrackingResponse | { message?: string };
            };
            if (!response.ok || !payload.success) {
                throw new Error(
                    (payload.data as { message?: string })?.message ??
                        i18n.trackingError,
                );
            }
            tracking = payload.data as TrackingResponse;
        } catch (error) {
            trackingError =
                error instanceof Error ? error.message : i18n.trackingError;
        } finally {
            loadingTracking = false;
        }
    }

    onMount(() => {
        if (openAdjustDeficit && transaction.actions.adjustDeficit)
            actionDialog = { kind: 'adjust-deficit', data: transaction.actions.data };
        void loadTracking();
    });
</script>

<div class="kiriof-shadcn w-full">
    <Toolbar toolbar={bootstrap.toolbar}>
        {#if transaction.shipment.printUrl}
            <Button onclick={() => (printPreviewOpen = true)}>
                <IconPrinter data-icon="inline-start" />
                {i18n.printLabel}
            </Button>
        {/if}
        {#if transaction.supportsLiveTracking}
            <Button
                variant="outline"
                onclick={() => void loadTracking()}
                disabled={loadingTracking}
            >
                <IconRoute data-icon="inline-start" />
                {i18n.liveTracking}
            </Button>
        {/if}
    </Toolbar>

    {#if bootstrap.bootstrapError}
        <div class="mb-4 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="alert">{bootstrap.bootstrapError}</div>
    {/if}

    <div class="!grid !items-start gap-4 xl:grid-cols-[minmax(0,1fr)_22.5rem]">
        <main class="!grid min-w-0 gap-4">
            <KiriofCard>
                <Card.Header class="p-3 border-b">
                    <Card.Title
                        ><StatusBadge
                            label={transaction.status.label}
                            tone={transaction.status.tone}
                        /></Card.Title
                    >
                    {#if transaction.pickupNumber}
                        <Card.Action>
                            <div
                                class="!flex items-baseline gap-1.5 text-xs text-muted-foreground"
                            >
                                <span>{i18n.pickupId}</span><strong
                                    class="text-sm font-semibold text-foreground"
                                    >{transaction.pickupNumber}</strong
                                >
                            </div>
                        </Card.Action>
                    {/if}
                </Card.Header>
                <Card.Content class="!px-4 !pt-3 !pb-4">
                    <div
                        class="!flex items-baseline gap-2 text-xs text-muted-foreground"
                    >
                        <strong class="text-sm font-semibold text-foreground"
                            >{transaction.orderNumber}</strong
                        ><span>{transaction.createdAt}</span>
                    </div>
                    <div class="relative mt-4 !grid grid-cols-3 pb-1">
                        <span
                            class="absolute top-[13px] left-7 right-7 h-0.5 bg-border"
                        ></span>
                        {#each transaction.steps as step, index}
                            {#if index === transaction.steps.length - 1}
                                <div
                                    class="absolute top-0 right-0 !grid justify-items-end text-right text-muted-foreground"
                                >
                                    <span
                                        class="relative z-10 !grid size-7 place-items-center rounded-full border border-border bg-background"
                                        >{#if step.completed}<IconCheck
                                            />{:else}<IconBox />{/if}</span
                                    >
                                    <strong
                                        class="mt-2 text-xs font-semibold text-foreground"
                                        >{step.label}</strong
                                    >
                                    <small
                                        class="mt-1 min-h-4 text-[11px] leading-tight"
                                        >{step.date || "—"}</small
                                    >
                                </div>
                            {:else}
                                <div class="relative text-muted-foreground">
                                    <span
                                        class="relative z-10 !grid size-7 place-items-center rounded-full border border-border bg-background"
                                        >{#if step.completed}<IconCheck
                                            />{:else}<IconBox />{/if}</span
                                    >
                                    {#if step.completed}<span
                                            class="absolute top-[13px] left-7 right-0 h-0.5 origin-left scale-x-0 bg-primary motion-reduce:scale-x-100 motion-reduce:animate-none animate-[kiriof-detail-step-progress_520ms_ease-out_forwards]"
                                        ></span>{/if}
                                    <strong
                                        class="mt-2 !block truncate text-xs font-semibold text-foreground"
                                        >{step.label}</strong
                                    >
                                    <small
                                        class="mt-1 !block min-h-4 text-[11px] leading-tight"
                                        >{step.date || "—"}</small
                                    >
                                </div>
                            {/if}
                        {/each}
                    </div>
                </Card.Content>
            </KiriofCard>

            <section
                class="!grid gap-4 md:grid-cols-2"
                aria-label={i18n.senderRecipientDetails}
            >
                <KiriofCard>
                    <Card.Header class="p-3 border-b">
                        <Card.Title><IconMapPin />{i18n.sender}</Card.Title>
                        {#if transaction.actions.changeOrigin}
                            <Card.Action>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onclick={() =>
                                        (actionDialog = {
                                            kind: "origin",
                                            data: transaction.actions.data,
                                        })}
                                >
                                    <IconMapPin data-icon="inline-start" />
                                    {i18n.changeOrigin}
                                </Button>
                            </Card.Action>
                        {/if}
                    </Card.Header>
                    <Card.Content
                        class="!grid gap-2 !px-4 !py-4 text-sm leading-relaxed text-muted-foreground"
                    >
                        <strong class="text-sm font-semibold text-foreground"
                            >{transaction.sender.name}</strong
                        >
                        {#if transaction.sender.phone}<a
                                class="!inline-flex !items-center gap-1.5 text-primary no-underline"
                                href={`tel:${transaction.sender.phone}`}
                                ><IconPhone />{formatPhone(
                                    transaction.sender.phone,
                                )}</a
                            >{/if}
                        {#each transaction.sender.address as line}<span
                                >{line}</span
                            >{/each}
                    </Card.Content>
                </KiriofCard>

                <KiriofCard>
                    <Card.Header class="p-3 border-b">
                        <Card.Title><IconUser />{i18n.recipient}</Card.Title>
                        {#if transaction.recipient.phone}
                            <Card.Action
                                ><Button
                                    size="sm"
                                    href={`tel:${transaction.recipient.phone}`}
                                    ><IconPhone
                                        data-icon="inline-start"
                                    />{i18n.contactCustomer}</Button
                                ></Card.Action
                            >
                        {/if}
                    </Card.Header>
                    <Card.Content
                        class="!grid gap-2 !px-4 !py-4 text-sm leading-relaxed text-muted-foreground"
                    >
                        <strong class="text-sm font-semibold text-foreground"
                            >{transaction.recipient.name}</strong
                        >
                        {#if transaction.recipient.phone}<a
                                class="!inline-flex !items-center gap-1.5 text-primary no-underline"
                                href={`tel:${transaction.recipient.phone}`}
                                ><IconPhone />{formatPhone(
                                    transaction.recipient.phone,
                                )}</a
                            >{/if}
                        {#each transaction.recipient.address as line}<span
                                >{line}</span
                            >{/each}
                    </Card.Content>
                </KiriofCard>
            </section>

            <KiriofCard>
                <Card.Header class="!flex !items-center !justify-between gap-3 p-3 border-b">
                    <Card.Title><IconPackage />{i18n.detailPackageProduct ?? 'Detail Package & Product'}</Card.Title>
                    <div class="!flex shrink-0 items-center gap-2 text-xs">
                        <span class="rounded-full bg-sky-100 px-2 py-1 font-semibold text-sky-900">{transaction.package.weight} g</span>
                        <span class="rounded-full bg-amber-100 px-2 py-1 font-semibold text-amber-900">{transaction.package.length} × {transaction.package.width} × {transaction.package.height} cm</span>
                    </div>
                </Card.Header>
                <Card.Content class="!grid gap-3 !px-4 !py-4">
                    <div class="!grid gap-2">
                        {#if transaction.items.length === 0}<p class="m-0 text-sm text-muted-foreground">—</p>{/if}
                        {#each transaction.items as item}
                            <div class="!flex min-w-0 !items-center gap-3 rounded-lg border border-border p-3 text-sm">
                                <span class="!grid size-10 shrink-0 place-items-center rounded-md bg-muted text-muted-foreground"><IconBox /></span>
                                <span class="!grid min-w-0 flex-1 gap-0.5"><strong class="truncate font-semibold text-foreground">{item.name}</strong>{#if item.sku}<small class="text-xs text-muted-foreground">SKU: {item.sku}</small>{/if}</span>
                                <span class="shrink-0 text-muted-foreground">× {item.quantity}</span>
                                <strong class="shrink-0 font-semibold text-foreground">{currency(item.total)}</strong>
                            </div>
                        {/each}
                    </div>
                    <div class="!flex !items-center !justify-between gap-3 rounded-lg border border-border p-3 text-sm">
                        <span class="!flex min-w-0 items-center gap-2"><IconExternalLink class="size-4 shrink-0 text-muted-foreground" /><strong class="truncate text-foreground">{transaction.orderNumber}</strong></span>
                        {#if transaction.orderUrl}<Button variant="outline" size="sm" href={transaction.orderUrl} target="_blank" rel="noopener noreferrer"><IconExternalLink data-icon="inline-start" />{i18n.orderDetail ?? 'Order Detail'}</Button>{/if}
                    </div>
                    {#if transaction.notes.length > 0}
                        <div class="!grid gap-2 rounded-lg border border-border p-3 text-sm text-muted-foreground">
                            {#each transaction.notes as note}<div class="!grid gap-1"><strong class="text-xs text-muted-foreground">{note.label}</strong><p class="m-0 leading-relaxed text-foreground">{note.content}</p></div>{/each}
                        </div>
                    {/if}
                </Card.Content>
            </KiriofCard>
        </main>

        <aside class="!grid min-w-0 gap-4">
            <KiriofCard class="kiriof-shipment-card">
            <Card.Header class="p-3 border-b">
                    <Card.Title class="min-w-0 flex-1"
                        ><IconTruck />{i18n.shipment}</Card.Title
                    >
                    <div
                        class="!flex !max-w-full flex-wrap !justify-end gap-1.5"
                    >
                        <StatusBadge
                            label={transaction.paymentLabel}
                            tone={transaction.isCod ? "info" : "neutral"}
                        /><StatusBadge
                            label={i18n.pickup}
                            tone="warning"
                        />{#if transaction.shipment.paymentStatus}<StatusBadge
                                label={transaction.shipment.paymentStatus}
                                tone={transaction.shipment.paymentStatus ===
                                i18n.unpaid
                                    ? "warning"
                                    : "success"}
                            />{/if}
                    </div>
                </Card.Header>
                <Card.Content class="!grid min-w-0 gap-4 !px-4 !py-4">
                    <div
                        class="!grid min-w-0 grid-cols-[auto_minmax(0,1fr)] !items-center gap-3 rounded-lg border border-border p-2"
                    >
                        {#if courierImage(transaction.shipment.courier.code, transaction.shipment.courier.service)}
                            <img
                                class="kiriof-courier-logo !shrink-0"
                                src={courierImage(
                                    transaction.shipment.courier.code,
                                    transaction.shipment.courier.service,
                                )}
                                alt=""
                            />
                        {/if}
                        <div
                            class="!flex min-w-0 flex-1 flex-col !justify-center"
                        >
                            <strong
                                class="truncate text-sm font-semibold text-foreground"
                                >{transaction.shipment.courier.service ||
                                    "—"}</strong
                            >
                            <CopyableValue
                                label={i18n.airwaybill}
                                value={transaction.shipment.awb}
                                copyLabel={i18n.copyAwb}
                                copiedLabel={i18n.copied}
                            />
                        </div>
                    </div>
                    <dl class="!grid min-w-0 gap-2 text-sm">
                        <div
                            class="!flex min-w-0 !items-center !justify-between gap-4 text-muted-foreground"
                        >
                            <dt class="min-w-0">{i18n.orderId}</dt>
                            <dd
                                class="m-0 shrink-0 text-right font-semibold text-foreground"
                            >
                                {transaction.orderId}
                            </dd>
                        </div>
                        <div
                            class="!flex min-w-0 !items-center !justify-between gap-4 text-muted-foreground"
                        >
                            <dt class="min-w-0">{i18n.orderSubtotal}</dt>
                            <dd
                                class="m-0 shrink-0 text-right font-semibold text-foreground"
                            >
                                {currency(transaction.shipment.costs.subtotal)}
                            </dd>
                        </div>
                        <div
                            class="!flex min-w-0 !items-center !justify-between gap-4 text-muted-foreground"
                        >
                            <dt class="min-w-0">{i18n.totalShipping}</dt>
                            <dd
                                class="m-0 shrink-0 text-right font-semibold text-foreground"
                            >
                                {currency(
                                    transaction.shipment.costs.totalShipping,
                                )}
                            </dd>
                        </div>
                        <div
                            class="!flex min-w-0 !items-center !justify-between gap-4 pl-4 text-muted-foreground"
                        >
                            <dt class="min-w-0">{i18n.actualShipping}</dt>
                            <dd
                                class="m-0 shrink-0 text-right font-semibold text-foreground"
                            >
                                {currency(
                                    transaction.shipment.costs.actualShipping,
                                )}
                            </dd>
                        </div>
                        {#if transaction.shipment.costs.shippingDiscount > 0}
                            <div
                                class="!flex min-w-0 !items-center !justify-between gap-4 pl-4 text-muted-foreground"
                            >
                                <dt class="min-w-0">{i18n.shippingDiscount}</dt>
                                <dd
                                    class="m-0 shrink-0 text-right font-semibold text-emerald-700"
                                >
                                    −{currency(
                                        transaction.shipment.costs
                                            .shippingDiscount,
                                    )}
                                </dd>
                            </div>
                        {/if}
                        <div
                            class="!flex min-w-0 !items-center !justify-between gap-4 pl-4 text-muted-foreground"
                        >
                            <dt class="min-w-0">{i18n.shipping}</dt>
                            <dd
                                class="m-0 shrink-0 text-right font-semibold text-foreground"
                            >
                                {currency(transaction.shipment.costs.shipping)}
                            </dd>
                        </div>
                        {#if transaction.shipment.costs.insurance > 0}
                            <div
                                class="!flex min-w-0 !items-center !justify-between gap-4 pl-4 text-muted-foreground"
                            >
                                <dt class="min-w-0">{i18n.insurance}</dt>
                                <dd
                                    class="m-0 shrink-0 text-right font-semibold text-foreground"
                                >
                                    {currency(
                                        transaction.shipment.costs.insurance,
                                    )}
                                </dd>
                            </div>
                        {/if}
                        {#if transaction.shipment.costs.codFee > 0}
                            <div
                                class="!flex min-w-0 !items-center !justify-between gap-4 pl-4 text-muted-foreground"
                            >
                                <dt class="min-w-0">{i18n.codFee}</dt>
                                <dd
                                    class="m-0 shrink-0 text-right font-semibold text-foreground"
                                >
                                    {currency(
                                        transaction.shipment.costs.codFee,
                                    )}
                                </dd>
                            </div>
                        {/if}
                        <div
                            class="!flex min-w-0 !items-center !justify-between gap-4 border-t border-border pt-3"
                        >
                            <dt class="min-w-0 font-semibold text-foreground">
                                {i18n.total}
                            </dt>
                            <dd
                                class="m-0 shrink-0 text-right font-semibold text-foreground"
                            >
                                {currency(
                                    transaction.shipment.costs.orderTotal,
                                )}
                            </dd>
                        </div>
                        {#if transaction.isCod}
                            <div
                                class="!flex min-w-0 !items-center !justify-between gap-4 border-t border-border pt-3"
                            >
                                <dt
                                    class="min-w-0 font-semibold text-foreground"
                                >
                                    {i18n.codValue}
                                </dt>
                                <dd
                                    class="m-0 shrink-0 text-right font-semibold text-foreground"
                                >
                                    {currency(transaction.shipment.codValue)}
                                </dd>
                            </div>
                        {/if}
                    </dl>
                    {#if transaction.actions.adjustDeficit}
                        <Button
                            variant="outline"
                            onclick={() =>
                                (actionDialog = {
                                    kind: "adjust-deficit",
                                    data: transaction.actions.data,
                                })}
                            ><IconRefresh
                                data-icon="inline-start"
                            />{i18n.adjustDeficit}</Button
                        >
                    {/if}
                    {#if transaction.actions.cancelDeficit}
                        <Button
                            variant="destructive"
                            onclick={() =>
                                (actionDialog = {
                                    kind: "cancel-deficit",
                                    data: transaction.actions.data,
                                })}
                            ><IconX
                                data-icon="inline-start"
                            />{i18n.cancel}</Button
                        >
                    {/if}
                    {#if transaction.actions.cancel}
                        <Button
                            variant="destructive"
                            onclick={() =>
                                (actionDialog = {
                                    kind: "cancel",
                                    data: transaction.actions.data,
                                })}
                            ><IconX
                                data-icon="inline-start"
                            />{i18n.cancel}</Button
                        >
                    {/if}
                </Card.Content>
            </KiriofCard>

            {#if transaction.supportsLiveTracking}
                <KiriofCard>
                    <Card.Header class="p-3 border-b">
                        <Card.Title><IconRoute />{i18n.tracking}</Card.Title>
                    </Card.Header>
                    <Card.Content class="!px-4 !py-3"
                        >{#if loadingTracking}<p
                                class="text-sm text-muted-foreground"
                            >
                                {i18n.loadingTracking}
                            </p>{:else if trackingError}<p
                                class="text-sm text-destructive"
                                role="alert"
                            >
                                {trackingError}
                            </p>{:else if !tracking?.histories?.length}<p
                                class="text-sm text-muted-foreground"
                            >
                                {i18n.trackingEmpty}
                            </p>{:else}<ol
                                class="m-0 !grid list-none gap-0 p-0"
                            >
                                {#each tracking.histories as history}<li
                                        class="relative !grid grid-cols-[20px_minmax(0,1fr)] gap-2.5 py-2.5 not-last:after:absolute not-last:after:top-8 not-last:after:bottom-0 not-last:after:left-[9px] not-last:after:w-0.5 not-last:after:bg-border"
                                    >
                                        <span
                                            class="relative z-10 !grid size-5 place-items-center rounded-full bg-primary/10 text-primary"
                                            ><IconCircleCheck /></span
                                        >
                                        <div class="!grid gap-1">
                                            <strong
                                                class="text-xs leading-snug text-foreground"
                                                >{history.status}</strong
                                            ><small
                                                class="text-[11px] text-muted-foreground"
                                                >{history.created_at}</small
                                            >{#if history.driver}<small
                                                    class="text-[11px] text-muted-foreground"
                                                    >{history.driver}</small
                                                >{/if}
                                        </div>
                                    </li>{/each}
                            </ol>{/if}</Card.Content
                    >
                </KiriofCard>
            {/if}
        </aside>
    </div>
    <TransactionActionDialogs
        bind:action={actionDialog}
        locations={bootstrap.shipmentLocations}
        locationsUrl={bootstrap.locationsUrl}
        ajaxUrl={bootstrap.ajax.url}
        {i18n}
        onComplete={finishAction}
    />
    <PrintPreviewDialog bind:open={printPreviewOpen} orderIds={[transaction.orderId]} ajaxUrl={bootstrap.ajax.url} nonce={bootstrap.ajax.printPreviewNonce} {i18n} />
</div>
