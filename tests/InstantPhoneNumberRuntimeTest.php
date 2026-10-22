<?php
use PHPUnit\Framework\TestCase;
use KiriminAjaOfficial\Services\InstantPhoneNumber;

final class InstantPhoneNumberRuntimeTest extends TestCase {
    private function fixture(array $input = []): array {
        $lines = []; $status = 0;
        exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/fixtures/instant-phone-number-runtime.php') . ' ' . escapeshellarg(json_encode($input)) . ' 2>&1', $lines, $status);
        $this->assertSame(0, $status, implode("\n", $lines));
        return json_decode(implode("\n", $lines), true, 512, JSON_THROW_ON_ERROR);
    }

    public function test_malformed_prefixes_and_unbalanced_groups_are_not_repaired(): void {
        foreach (['6-281200002222', '0-81200002222', '62))))81200002222', '+62812(0000-2222', '+62 ()81200002222', '(+62)81200002222', '(0812))00002222', '((0812))00002222'] as $phone) {
            $this->assertSame('', InstantPhoneNumber::normalize($phone), $phone);
        }
    }

    public function test_formatted_contacts_quote_and_validate_without_mutating_sources(): void {
        $r = $this->fixture();
        $this->assertTrue($r['quote']['eligible'], $r['quote']['code']);
        $this->assertSame('6281200002222', $r['quote']['context']['recipient']['phone']);
        $this->assertSame('6281200001111', $r['quote']['context']['origin']['phone']);
        $this->assertSame($r['quote']['context'], $r['validated']['context']);
        $this->assertSame($r['before'], $r['after']);
        $this->assertSame(1, $r['calls']);
    }

    public function test_equivalent_formatting_keeps_quote_fingerprint_and_token_valid(): void {
        $r = $this->fixture(['validate_phone' => '6281200002222', 'validate_origin_phone' => '62 812-0000-1111']);
        $this->assertTrue($r['quote']['eligible']);
        $this->assertSame('', $r['error']);
        $this->assertSame($r['quote']['context']['fingerprint'], $r['validated']['context']['fingerprint']);
        $this->assertSame($r['quote'], $r['again']);
        $this->assertSame(1, $r['calls']);
    }

    public function test_plain_quote_validates_formatted_and_local_contacts_retain_08(): void {
        $r = $this->fixture(['phone' => '6281200002222', 'origin_phone' => '6281200001111', 'validate_phone' => '+62 (812) 0000-2222', 'validate_origin_phone' => '+62 (812) 0000-1111']);
        $this->assertTrue($r['quote']['eligible']);
        $this->assertSame('', $r['error']);
        $this->assertSame($r['quote']['context'], $r['validated']['context']);
        $r = $this->fixture(['phone' => '(0812) 0000-2222', 'origin_phone' => '(0812) 0000-1111']);
        $this->assertTrue($r['quote']['eligible']);
        $this->assertSame('081200002222', $r['validated']['context']['recipient']['phone']);
        $this->assertSame('081200001111', $r['validated']['context']['origin']['phone']);
    }

    public function test_normalizer_only_accepts_indonesian_ascii_phone_syntax(): void {
        foreach (['081200001111' => '081200001111', '6281200001111' => '6281200001111', '+62 (812) 0000-1111' => '6281200001111', ' (0812) 0000-1111 ' => '081200001111', '62 812 0000 1111' => '6281200001111'] as $raw => $expected) {
            $this->assertSame($expected, InstantPhoneNumber::normalize((string) $raw));
        }
        foreach (['', ' ', null, false, 6281200001111, [], new stdClass(), '+1 812 0000 1111', '81200001111', '006281200001111', '++6281200001111', '62+81200001111', '+08 1200001111', '6281200001111 ext 2', '6281200001111x2', '６２８１２００００１１１１', '62.812.0000.1111', "\t6281200001111", "6281200001111\n", "62\00081200001111", "62\u{00a0}81200001111"] as $raw) {
            $this->assertSame('', InstantPhoneNumber::normalize($raw));
        }
    }

    public function test_empty_invalid_and_out_of_bounds_phones_never_reuse_a_valid_contact(): void {
        foreach (['', null, false, [], 6281200001111, '6281200', '6281200001111111', "+62 812\n0000-2222"] as $phone) {
            $r = $this->fixture(['phone' => $phone]);
            $this->assertFalse($r['quote']['eligible']);
            $this->assertNull($r['validated']);
            $this->assertSame(0, $r['calls']);
        }
        foreach (['62812000', '62812000001111'] as $phone) {
            $r = $this->fixture(['phone' => $phone]);
            $this->assertTrue($r['quote']['eligible']);
            $this->assertSame($phone, $r['validated']['context']['recipient']['phone']);
        }
        foreach (['origin_phone' => '', 'origin' => []] as $key => $value) {
            $r = $this->fixture([$key => $value]);
            $this->assertSame('origin_invalid', $r['quote']['code']);
            $this->assertSame(0, $r['calls']);
        }
    }

    public function test_admin_context_normalizes_both_contacts_without_rewriting_snapshots(): void {
        $input = ['mode' => 'admin', 'address' => ['phone' => '+62 (812) 0000-2222'], 'origin' => ['origin_phone' => '+62 (812) 0000-1111']];
        $r = $this->fixture($input);
        $this->assertTrue($r['ok'], $r['error'] ?? '');
        $this->assertSame('6281200001111', $r['context']['origin']['phone']);
        $this->assertSame('6281200002222', $r['context']['package']['destination']['phone']);
        $this->assertSame($input['address']['phone'], $r['source']['address']['phone']);
        $this->assertSame($input['origin']['origin_phone'], json_decode($r['source']['origin'], true)['origin_phone']);
        $plain = $this->fixture(['mode' => 'admin', 'address' => ['phone' => '6281200002222'], 'origin' => ['origin_phone' => '6281200001111']]);
        $this->assertSame($r['context']['fingerprint'], $plain['context']['fingerprint']);
        $this->assertSame($plain['source']['shipping_info'], $r['source']['shipping_info']);
        foreach (['', null, 6281200001111, "\t6281200001111", '+1 812 0000-2222'] as $phone) {
            $invalid = $this->fixture(['mode' => 'admin', 'address' => ['phone' => $phone]]);
            $this->assertFalse($invalid['ok']);
        }
        $invalid = $this->fixture(['mode' => 'admin', 'origin' => ['origin_phone' => '', 'phone' => '6281200001111']]);
        $this->assertFalse($invalid['ok']);
    }

    public function test_address_length_has_its_own_buyer_safe_reason(): void {
        $r = $this->fixture(['address' => ['address_1' => str_repeat('a', 251)]]);
        $this->assertSame('recipient_address_invalid', $r['quote']['code']);
        $this->assertSame('recipient_address_invalid', $r['error']);
        $this->assertSame('recipient_address_invalid', $r['status']['code']);
        $this->assertSame('The shipping recipient address must contain 20 to 250 characters for Instant delivery.', $r['status']['message']);
        $this->assertSame(0, $r['calls']);
    }

    public function test_admin_empty_shipping_phone_preserves_current_billing_and_getter_fallbacks(): void {
        foreach (['', null, '   '] as $empty) {
            foreach ([
                ['billing' => ['phone' => '+62 (812) 0000-3333']],
                ['shipping_getter' => '+62 (812) 0000-3333', 'billing' => ['phone' => '+62 (812) 0000-4444']],
                ['billing' => ['phone' => null], 'billing_getter' => '+62 (812) 0000-3333'],
            ] as $fallback) {
                $r = $this->fixture(['mode' => 'admin_fallback', 'shipping' => ['phone' => $empty]] + $fallback);
                $this->assertTrue($r['ok'], $r['error'] ?? '');
                $this->assertSame('6281200003333', $r['context']['package']['destination']['phone']);
                $this->assertSame($r['before'], $r['after']);
            }
        }
        $r = $this->fixture(['mode' => 'admin_fallback', 'shipping' => ['phone' => '+62 (812) 0000-2222'], 'billing' => ['phone' => '+62 (812) 0000-3333']]);
        $this->assertTrue($r['ok'], $r['error'] ?? '');
        $this->assertSame('6281200002222', $r['context']['package']['destination']['phone']);
    }

    public function test_admin_invalid_selected_contacts_and_missing_current_contacts_never_reuse_history(): void {
        foreach (['+1 812 0000-2222', 'not a phone', "\t6281200001111", 6281200001111, [], false] as $invalid) {
            foreach ([
                ['shipping' => ['phone' => $invalid], 'shipping_getter' => '+62 (812) 0000-5555'],
                ['shipping' => ['phone' => null], 'shipping_getter' => $invalid],
                ['shipping' => ['phone' => ''], 'billing' => ['phone' => $invalid]],
                ['shipping' => ['phone' => ''], 'billing' => ['phone' => null], 'billing_getter' => $invalid],
            ] as $contacts) {
                $r = $this->fixture(['mode' => 'admin_fallback'] + $contacts);
                $this->assertFalse($r['ok']);
                $this->assertStringContainsString('current recipient', $r['error']);
                $this->assertSame($r['before'], $r['after']);
            }
        }
        foreach (['', null, '   '] as $empty) {
            $r = $this->fixture(['mode' => 'admin_fallback', 'shipping' => ['phone' => $empty], 'billing' => ['phone' => $empty]]);
            $this->assertFalse($r['ok']);
            $this->assertSame($r['before'], $r['after']);
        }
    }
}
