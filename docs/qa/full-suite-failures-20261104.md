# Full Laravel suite failures — 2026-11-04

649 tests; 3052 assertions; 48 errors; 48 failures; 7 warnings; 1 skipped; 4 risky.

These are unresolved results, not an assertion that the failures are harmless.

## Tests\Unit\AccountActivationCenterContractTest::test_stage_n1_has_one_center_for_customers_and_sellers_with_private_evidence

```text
Tests\Unit\AccountActivationCenterContractTest::test_stage_n1_has_one_center_for_customers_and_sellers_with_private_evidence
Failed asserting that '<?php\n
\n
use App\Http\Controllers\Admin\AdvancedSearchController;\n
use App\Http\Controllers\Admin\PaymentReviewController;\n
```

## Tests\Unit\AccountActivationCenterContractTest::test_customer_and_seller_activation_payloads_are_wired_to_the_unified_case

```text
Tests\Unit\AccountActivationCenterContractTest::test_customer_and_seller_activation_payloads_are_wired_to_the_unified_case
Failed asserting that '<?php\n
namespace App\Services;\n
\n
use App\User as LegacyUser;\n
```

## Tests\Unit\AccountActivationCenterServiceTest::test_required_evidence_blocks_then_allows_customer_activation_and_hides_conversation

```text
Tests\Unit\AccountActivationCenterServiceTest::test_required_evidence_blocks_then_allows_customer_activation_and_hides_conversation
Failed asserting that 0 is identical to 1.
/home/sigma-eg/git/sigma-release-qa.LaQbDf/tests/Unit/AccountActivationCenterServiceTest.php:88
```

## Tests\Unit\AdminOrderGatePhaseThreeTest::test_admin_assignment_saves_seller_insurance_shipping_and_audited_snapshot

```text
Tests\Unit\AdminOrderGatePhaseThreeTest::test_admin_assignment_saves_seller_insurance_shipping_and_audited_snapshot
TypeError: App\Services\AdminOrderGateService::__construct(): Argument #1 ($shipping) must be of type App\Services\OrderShippingDecisionService, Mockery_2_App_Services_SellerOrderInsuranceService given, called in /home/sigma-eg/git/sigma-release-qa.LaQbDf/tests/Unit/AdminOrderGatePhaseThreeTest.php on line 215
/home/sigma-eg/git/sigma-release-qa.LaQbDf/app/Services/AdminOrderGateService.php:22
/home/sigma-eg/git/sigma-release-qa.LaQbDf/tests/Unit/AdminOrderGatePhaseThreeTest.php:215
/home/sigma-eg/git/sigma-release-qa.LaQbDf/tests/Unit/AdminOrderGatePhaseThreeTest.php:83
```

## Tests\Unit\AdminOrderGatePhaseThreeTest::test_pending_review_is_hidden_and_unpaid_order_cannot_be_released

```text
Tests\Unit\AdminOrderGatePhaseThreeTest::test_pending_review_is_hidden_and_unpaid_order_cannot_be_released
TypeError: App\Services\AdminOrderGateService::__construct(): Argument #1 ($shipping) must be of type App\Services\OrderShippingDecisionService, Mockery_2_App_Services_SellerOrderInsuranceService given, called in /home/sigma-eg/git/sigma-release-qa.LaQbDf/tests/Unit/AdminOrderGatePhaseThreeTest.php on line 215
/home/sigma-eg/git/sigma-release-qa.LaQbDf/app/Services/AdminOrderGateService.php:22
/home/sigma-eg/git/sigma-release-qa.LaQbDf/tests/Unit/AdminOrderGatePhaseThreeTest.php:215
/home/sigma-eg/git/sigma-release-qa.LaQbDf/tests/Unit/AdminOrderGatePhaseThreeTest.php:106
```

## Tests\Unit\AdminOrderGatePhaseThreeTest::test_release_requires_both_decisions_and_then_sends_only_after_admin_audit

```text
Tests\Unit\AdminOrderGatePhaseThreeTest::test_release_requires_both_decisions_and_then_sends_only_after_admin_audit
TypeError: App\Services\AdminOrderGateService::__construct(): Argument #1 ($shipping) must be of type App\Services\OrderShippingDecisionService, Mockery_2_App_Services_SellerOrderInsuranceService given, called in /home/sigma-eg/git/sigma-release-qa.LaQbDf/tests/Unit/AdminOrderGatePhaseThreeTest.php on line 215
/home/sigma-eg/git/sigma-release-qa.LaQbDf/app/Services/AdminOrderGateService.php:22
/home/sigma-eg/git/sigma-release-qa.LaQbDf/tests/Unit/AdminOrderGatePhaseThreeTest.php:215
/home/sigma-eg/git/sigma-release-qa.LaQbDf/tests/Unit/AdminOrderGatePhaseThreeTest.php:113
```

## Tests\Unit\AdminOrderGatePhaseThreeTest::test_open_investigation_freezes_flow_and_is_idempotent

```text
Tests\Unit\AdminOrderGatePhaseThreeTest::test_open_investigation_freezes_flow_and_is_idempotent
TypeError: App\Services\AdminOrderGateService::__construct(): Argument #1 ($shipping) must be of type App\Services\OrderShippingDecisionService, Mockery_2_App_Services_SellerOrderInsuranceService given, called in /home/sigma-eg/git/sigma-release-qa.LaQbDf/tests/Unit/AdminOrderGatePhaseThreeTest.php on line 215
/home/sigma-eg/git/sigma-release-qa.LaQbDf/app/Services/AdminOrderGateService.php:22
/home/sigma-eg/git/sigma-release-qa.LaQbDf/tests/Unit/AdminOrderGatePhaseThreeTest.php:215
/home/sigma-eg/git/sigma-release-qa.LaQbDf/tests/Unit/AdminOrderGatePhaseThreeTest.php:130
```

## Tests\Unit\AdminOrderGatePhaseThreeTest::test_investigation_messages_go_only_through_admin_support_channels

```text
Tests\Unit\AdminOrderGatePhaseThreeTest::test_investigation_messages_go_only_through_admin_support_channels
TypeError: App\Services\AdminOrderGateService::__construct(): Argument #1 ($shipping) must be of type App\Services\OrderShippingDecisionService, Mockery_2_App_Services_SellerOrderInsuranceService given, called in /home/sigma-eg/git/sigma-release-qa.LaQbDf/tests/Unit/AdminOrderGatePhaseThreeTest.php on line 215
/home/sigma-eg/git/sigma-release-qa.LaQbDf/app/Services/AdminOrderGateService.php:22
/home/sigma-eg/git/sigma-release-qa.LaQbDf/tests/Unit/AdminOrderGatePhaseThreeTest.php:215
/home/sigma-eg/git/sigma-release-qa.LaQbDf/tests/Unit/AdminOrderGatePhaseThreeTest.php:143
```

## Tests\Unit\AdminOrderGatePhaseThreeTest::test_final_fake_order_decision_schedules_purchase_refund_and_closes_investigation

```text
Tests\Unit\AdminOrderGatePhaseThreeTest::test_final_fake_order_decision_schedules_purchase_refund_and_closes_investigation
TypeError: App\Services\AdminOrderGateService::__construct(): Argument #1 ($shipping) must be of type App\Services\OrderShippingDecisionService, Mockery_2_App_Services_SellerOrderInsuranceService given, called in /home/sigma-eg/git/sigma-release-qa.LaQbDf/tests/Unit/AdminOrderGatePhaseThreeTest.php on line 215
/home/sigma-eg/git/sigma-release-qa.LaQbDf/app/Services/AdminOrderGateService.php:22
/home/sigma-eg/git/sigma-release-qa.LaQbDf/tests/Unit/AdminOrderGatePhaseThreeTest.php:215
/home/sigma-eg/git/sigma-release-qa.LaQbDf/tests/Unit/AdminOrderGatePhaseThreeTest.php:156
```

## Tests\Unit\AdminOrderGatePhaseThreeTest::test_phase_three_routes_migration_dashboard_and_feature_are_present

```text
Tests\Unit\AdminOrderGatePhaseThreeTest::test_phase_three_routes_migration_dashboard_and_feature_are_present
Failed asserting that '@extends('layouts.vendor.app')\n
@section('title', translate('order_List'))\n
\n
@push('css_or_js')\n
```

## Tests\Unit\AdminWorkspaceTest::test_phone_formats_link_to_the_exact_customer_and_vendor

```text
Tests\Unit\AdminWorkspaceTest::test_phone_formats_link_to_the_exact_customer_and_vendor
Illuminate\Database\QueryException: SQLSTATE[HY000]: General error: 1 no such column: c.admin_id (Connection: sqlite, Database: :memory:, SQL: select * from (select * from (select 'contact' as kind, id, name as sender, COALESCE(sender_type, CASE WHEN subject LIKE '%تاجر%' OR EXISTS (SELECT 1 FROM sellers s WHERE s.email = contacts.email AND s.email <> '') THEN 'vendor' ELSE 'customer' END) as audience, subject, COALESCE((SELECT body FROM contact_conversation_messages m WHERE m.contact_id=contacts.id AND m.sender='requester' ORDER BY m.id DESC LIMIT 1),message) as preview, updated_at, CASE WHEN seen = 0 THEN 1 ELSE 0 END as unread from "contacts") union all select * from (select 'ticket' as kind, t.id, COALESCE(u.f_name,'') || ' ' || COALESCE(u.l_name,'') as sender, 'customer' as audience, t.subject, COALESCE((SELECT customer_message FROM support_ticket_convs m WHERE m.support_ticket_id=t.id AND m.customer_message IS NOT NULL ORDER BY m.id DESC LIMIT 1),t.description) as preview, t.updated_at, CASE WHEN t.admin_read_at IS NULL OR EXISTS (SELECT 1 FROM support_ticket_convs c WHERE c.support_ticket_id=t.id AND (c.customer_message IS NOT NULL OR ((c.admin_id IS NULL OR c.admin_id = 0) AND c.admin_message IS NULL)) AND c.id > COALESCE(t.admin_read_message_id,0)) THEN 1 ELSE 0 END as unread from "support_tickets" as "t" left join "users" as "u" on "u"."id" = "t"."customer_id" where ("t"."purpose" is null or "t"."purpose" != account_activation)) union all select * from (select 'vendor' as kind, t.id, COALESCE(s.f_name,'') || ' ' || COALESCE(s.l_name,'') as sender, 'vendor' as audience, t.subject, COALESCE((SELECT body FROM vendor_support_messages m WHERE m.vendor_support_thread_id=t.id ORDER BY m.id DESC LIMIT 1),'') as preview, t.updated_at, 0 as unread from "vendor_support_threads" as "t" left join "sellers" as "s" on "s"."id" = "t"."seller_id") union all select * from (select 'chat' as kind, c.user_id as id, MAX(COALESCE(u.f_name,'') || ' ' || COALESCE(u.l_name,'')) as sender, 'customer' as audience, '' as subject, COALESCE((SELECT message FROM chattings m WHERE m.user_id=c.user_id AND m.admin_id=0 ORDER BY m.id DESC LIMIT 1),'') as preview, MAX(c.created_at) as updated_at, SUM(CASE WHEN c.sent_by_customer = 1 AND c.seen_by_admin = 0 THEN 1 ELSE 0 END) as unread from "chattings" as "c" left join "users" as "u" on "u"."id" = "c"."user_id" where "c"."admin_id" = 0 and "c"."user_id" is not null group by "c"."user_id")) as "conversations" where ("sender" like %01012345678% or "subject" like %01012345678% or "preview" like %01012345678% or exists (select 1 from "contact_conversation_messages" where "contact_id" = "conversations"."id" and "conversations"."kind" = contact and "body" like %01012345678%) or exists (select 1 from "vendor_support_messages" where "vendor_support_thread_id" = "conversations"."id" and "conversations"."kind" = vendor and "body" like %01012345678%) or exists (select 1 from "support_ticket_convs" where "support_ticket_id" = "conversations"."id" and "conversations"."kind" = ticket and ("customer_message" like %01012345678% or "admin_message" like %01012345678%)) or exists (select 1 from "chattings" where "user_id" = "conversations"."id" and "admin_id" = 0 and "conversations"."kind" = chat and "message" like %01012345678%)) order by "updated_at" desc limit 6)
/home/sigma-eg/git/sigma-release-qa.LaQbDf/vendor/laravel/framework/src/Illuminate/Database/Connection.php:838
/home/sigma-eg/git/sigma-release-qa.LaQbDf/vendor/laravel/framework/src/Illuminate/Database/Connection.php:794
/home/sigma-eg/git/sigma-release-qa.LaQbDf/vendor/laravel/framework/src/Illuminate/Database/Connection.php:411
```

## Tests\Unit\AdminWorkspaceTest::test_inbox_and_center_share_counts_and_exclude_activation_tickets

```text
Tests\Unit\AdminWorkspaceTest::test_inbox_and_center_share_counts_and_exclude_activation_tickets
Illuminate\Database\QueryException: SQLSTATE[HY000]: General error: 1 no such column: c.admin_id (Connection: sqlite, Database: :memory:, SQL: select count(*) as aggregate from (select * from (select 'contact' as kind, id, name as sender, COALESCE(sender_type, CASE WHEN subject LIKE '%تاجر%' OR EXISTS (SELECT 1 FROM sellers s WHERE s.email = contacts.email AND s.email <> '') THEN 'vendor' ELSE 'customer' END) as audience, subject, COALESCE((SELECT body FROM contact_conversation_messages m WHERE m.contact_id=contacts.id AND m.sender='requester' ORDER BY m.id DESC LIMIT 1),message) as preview, updated_at, CASE WHEN seen = 0 THEN 1 ELSE 0 END as unread from "contacts") union all select * from (select 'ticket' as kind, t.id, COALESCE(u.f_name,'') || ' ' || COALESCE(u.l_name,'') as sender, 'customer' as audience, t.subject, COALESCE((SELECT customer_message FROM support_ticket_convs m WHERE m.support_ticket_id=t.id AND m.customer_message IS NOT NULL ORDER BY m.id DESC LIMIT 1),t.description) as preview, t.updated_at, CASE WHEN t.admin_read_at IS NULL OR EXISTS (SELECT 1 FROM support_ticket_convs c WHERE c.support_ticket_id=t.id AND (c.customer_message IS NOT NULL OR ((c.admin_id IS NULL OR c.admin_id = 0) AND c.admin_message IS NULL)) AND c.id > COALESCE(t.admin_read_message_id,0)) THEN 1 ELSE 0 END as unread from "support_tickets" as "t" left join "users" as "u" on "u"."id" = "t"."customer_id" where ("t"."purpose" is null or "t"."purpose" != account_activation)) union all select * from (select 'vendor' as kind, t.id, COALESCE(s.f_name,'') || ' ' || COALESCE(s.l_name,'') as sender, 'vendor' as audience, t.subject, COALESCE((SELECT body FROM vendor_support_messages m WHERE m.vendor_support_thread_id=t.id ORDER BY m.id DESC LIMIT 1),'') as preview, t.updated_at, 0 as unread from "vendor_support_threads" as "t" left join "sellers" as "s" on "s"."id" = "t"."seller_id") union all select * from (select 'chat' as kind, c.user_id as id, MAX(COALESCE(u.f_name,'') || ' ' || COALESCE(u.l_name,'')) as sender, 'customer' as audience, '' as subject, COALESCE((SELECT message FROM chattings m WHERE m.user_id=c.user_id AND m.admin_id=0 ORDER BY m.id DESC LIMIT 1),'') as preview, MAX(c.created_at) as updated_at, SUM(CASE WHEN c.sent_by_customer = 1 AND c.seen_by_admin = 0 THEN 1 ELSE 0 END) as unread from "chattings" as "c" left join "users" as "u" on "u"."id" = "c"."user_id" where "c"."admin_id" = 0 and "c"."user_id" is not null group by "c"."user_id")) as "conversations")
/home/sigma-eg/git/sigma-release-qa.LaQbDf/vendor/laravel/framework/src/Illuminate/Database/Connection.php:838
/home/sigma-eg/git/sigma-release-qa.LaQbDf/vendor/laravel/framework/src/Illuminate/Database/Connection.php:794
/home/sigma-eg/git/sigma-release-qa.LaQbDf/vendor/laravel/framework/src/Illuminate/Database/Connection.php:411
```

## Tests\Unit\AdminWorkspaceTest::test_search_finds_message_bodies_and_correct_conversation_routes

```text
Tests\Unit\AdminWorkspaceTest::test_search_finds_message_bodies_and_correct_conversation_routes
Illuminate\Database\QueryException: SQLSTATE[HY000]: General error: 1 no such column: c.admin_id (Connection: sqlite, Database: :memory:, SQL: select * from (select * from (select 'contact' as kind, id, name as sender, COALESCE(sender_type, CASE WHEN subject LIKE '%تاجر%' OR EXISTS (SELECT 1 FROM sellers s WHERE s.email = contacts.email AND s.email <> '') THEN 'vendor' ELSE 'customer' END) as audience, subject, COALESCE((SELECT body FROM contact_conversation_messages m WHERE m.contact_id=contacts.id AND m.sender='requester' ORDER BY m.id DESC LIMIT 1),message) as preview, updated_at, CASE WHEN seen = 0 THEN 1 ELSE 0 END as unread from "contacts") union all select * from (select 'ticket' as kind, t.id, COALESCE(u.f_name,'') || ' ' || COALESCE(u.l_name,'') as sender, 'customer' as audience, t.subject, COALESCE((SELECT customer_message FROM support_ticket_convs m WHERE m.support_ticket_id=t.id AND m.customer_message IS NOT NULL ORDER BY m.id DESC LIMIT 1),t.description) as preview, t.updated_at, CASE WHEN t.admin_read_at IS NULL OR EXISTS (SELECT 1 FROM support_ticket_convs c WHERE c.support_ticket_id=t.id AND (c.customer_message IS NOT NULL OR ((c.admin_id IS NULL OR c.admin_id = 0) AND c.admin_message IS NULL)) AND c.id > COALESCE(t.admin_read_message_id,0)) THEN 1 ELSE 0 END as unread from "support_tickets" as "t" left join "users" as "u" on "u"."id" = "t"."customer_id" where ("t"."purpose" is null or "t"."purpose" != account_activation)) union all select * from (select 'vendor' as kind, t.id, COALESCE(s.f_name,'') || ' ' || COALESCE(s.l_name,'') as sender, 'vendor' as audience, t.subject, COALESCE((SELECT body FROM vendor_support_messages m WHERE m.vendor_support_thread_id=t.id ORDER BY m.id DESC LIMIT 1),'') as preview, t.updated_at, 0 as unread from "vendor_support_threads" as "t" left join "sellers" as "s" on "s"."id" = "t"."seller_id") union all select * from (select 'chat' as kind, c.user_id as id, MAX(COALESCE(u.f_name,'') || ' ' || COALESCE(u.l_name,'')) as sender, 'customer' as audience, '' as subject, COALESCE((SELECT message FROM chattings m WHERE m.user_id=c.user_id AND m.admin_id=0 ORDER BY m.id DESC LIMIT 1),'') as preview, MAX(c.created_at) as updated_at, SUM(CASE WHEN c.sent_by_customer = 1 AND c.seen_by_admin = 0 THEN 1 ELSE 0 END) as unread from "chattings" as "c" left join "users" as "u" on "u"."id" = "c"."user_id" where "c"."admin_id" = 0 and "c"."user_id" is not null group by "c"."user_id")) as "conversations" where ("sender" like %فاتورة جديدة% or "subject" like %فاتورة جديدة% or "preview" like %فاتورة جديدة% or exists (select 1 from "contact_conversation_messages" where "contact_id" = "conversations"."id" and "conversations"."kind" = contact and "body" like %فاتورة جديدة%) or exists (select 1 from "vendor_support_messages" where "vendor_support_thread_id" = "conversations"."id" and "conversations"."kind" = vendor and "body" like %فاتورة جديدة%) or exists (select 1 from "support_ticket_convs" where "support_ticket_id" = "conversations"."id" and "conversations"."kind" = ticket and ("customer_message" like %فاتورة جديدة% or "admin_message" like %فاتورة جديدة%)) or exists (select 1 from "chattings" where "user_id" = "conversations"."id" and "admin_id" = 0 and "conversations"."kind" = chat and "message" like %فاتورة جديدة%)) order by "updated_at" desc limit 6)
/home/sigma-eg/git/sigma-release-qa.LaQbDf/vendor/laravel/framework/src/Illuminate/Database/Connection.php:838
/home/sigma-eg/git/sigma-release-qa.LaQbDf/vendor/laravel/framework/src/Illuminate/Database/Connection.php:794
/home/sigma-eg/git/sigma-release-qa.LaQbDf/vendor/laravel/framework/src/Illuminate/Database/Connection.php:411
```

## Tests\Unit\AdminWorkspaceTest::test_full_names_references_and_new_pages_respect_permissions

```text
Tests\Unit\AdminWorkspaceTest::test_full_names_references_and_new_pages_respect_permissions
Illuminate\Database\QueryException: SQLSTATE[HY000]: General error: 1 no such column: c.admin_id (Connection: sqlite, Database: :memory:, SQL: select * from (select * from (select 'contact' as kind, id, name as sender, COALESCE(sender_type, CASE WHEN subject LIKE '%تاجر%' OR EXISTS (SELECT 1 FROM sellers s WHERE s.email = contacts.email AND s.email <> '') THEN 'vendor' ELSE 'customer' END) as audience, subject, COALESCE((SELECT body FROM contact_conversation_messages m WHERE m.contact_id=contacts.id AND m.sender='requester' ORDER BY m.id DESC LIMIT 1),message) as preview, updated_at, CASE WHEN seen = 0 THEN 1 ELSE 0 END as unread from "contacts") union all select * from (select 'ticket' as kind, t.id, COALESCE(u.f_name,'') || ' ' || COALESCE(u.l_name,'') as sender, 'customer' as audience, t.subject, COALESCE((SELECT customer_message FROM support_ticket_convs m WHERE m.support_ticket_id=t.id AND m.customer_message IS NOT NULL ORDER BY m.id DESC LIMIT 1),t.description) as preview, t.updated_at, CASE WHEN t.admin_read_at IS NULL OR EXISTS (SELECT 1 FROM support_ticket_convs c WHERE c.support_ticket_id=t.id AND (c.customer_message IS NOT NULL OR ((c.admin_id IS NULL OR c.admin_id = 0) AND c.admin_message IS NULL)) AND c.id > COALESCE(t.admin_read_message_id,0)) THEN 1 ELSE 0 END as unread from "support_tickets" as "t" left join "users" as "u" on "u"."id" = "t"."customer_id" where ("t"."purpose" is null or "t"."purpose" != account_activation)) union all select * from (select 'vendor' as kind, t.id, COALESCE(s.f_name,'') || ' ' || COALESCE(s.l_name,'') as sender, 'vendor' as audience, t.subject, COALESCE((SELECT body FROM vendor_support_messages m WHERE m.vendor_support_thread_id=t.id ORDER BY m.id DESC LIMIT 1),'') as preview, t.updated_at, 0 as unread from "vendor_support_threads" as "t" left join "sellers" as "s" on "s"."id" = "t"."seller_id") union all select * from (select 'chat' as kind, c.user_id as id, MAX(COALESCE(u.f_name,'') || ' ' || COALESCE(u.l_name,'')) as sender, 'customer' as audience, '' as subject, COALESCE((SELECT message FROM chattings m WHERE m.user_id=c.user_id AND m.admin_id=0 ORDER BY m.id DESC LIMIT 1),'') as preview, MAX(c.created_at) as updated_at, SUM(CASE WHEN c.sent_by_customer = 1 AND c.seen_by_admin = 0 THEN 1 ELSE 0 END) as unread from "chattings" as "c" left join "users" as "u" on "u"."id" = "c"."user_id" where "c"."admin_id" = 0 and "c"."user_id" is not null group by "c"."user_id")) as "conversations" where ("sender" like %أحمد محمد% or "subject" like %أحمد محمد% or "preview" like %أحمد محمد% or exists (select 1 from "contact_conversation_messages" where "contact_id" = "conversations"."id" and "conversations"."kind" = contact and "body" like %أحمد محمد%) or exists (select 1 from "vendor_support_messages" where "vendor_support_thread_id" = "conversations"."id" and "conversations"."kind" = vendor and "body" like %أحمد محمد%) or exists (select 1 from "support_ticket_convs" where "support_ticket_id" = "conversations"."id" and "conversations"."kind" = ticket and ("customer_message" like %أحمد محمد% or "admin_message" like %أحمد محمد%)) or exists (select 1 from "chattings" where "user_id" = "conversations"."id" and "admin_id" = 0 and "conversations"."kind" = chat and "message" like %أحمد محمد%)) order by "updated_at" desc limit 6)
/home/sigma-eg/git/sigma-release-qa.LaQbDf/vendor/laravel/framework/src/Illuminate/Database/Connection.php:838
/home/sigma-eg/git/sigma-release-qa.LaQbDf/vendor/laravel/framework/src/Illuminate/Database/Connection.php:794
/home/sigma-eg/git/sigma-release-qa.LaQbDf/vendor/laravel/framework/src/Illuminate/Database/Connection.php:411
```

## Tests\Unit\AdminWorkspaceTest::test_totals_and_pending_reviews_are_distinct_and_blade_renders

```text
Tests\Unit\AdminWorkspaceTest::test_totals_and_pending_reviews_are_distinct_and_blade_renders
Illuminate\Database\QueryException: SQLSTATE[HY000]: General error: 1 no such column: c.admin_id (Connection: sqlite, Database: :memory:, SQL: select sum("unread") as aggregate from (select * from (select 'contact' as kind, id, name as sender, COALESCE(sender_type, CASE WHEN subject LIKE '%تاجر%' OR EXISTS (SELECT 1 FROM sellers s WHERE s.email = contacts.email AND s.email <> '') THEN 'vendor' ELSE 'customer' END) as audience, subject, COALESCE((SELECT body FROM contact_conversation_messages m WHERE m.contact_id=contacts.id AND m.sender='requester' ORDER BY m.id DESC LIMIT 1),message) as preview, updated_at, CASE WHEN seen = 0 THEN 1 ELSE 0 END as unread from "contacts") union all select * from (select 'ticket' as kind, t.id, COALESCE(u.f_name,'') || ' ' || COALESCE(u.l_name,'') as sender, 'customer' as audience, t.subject, COALESCE((SELECT customer_message FROM support_ticket_convs m WHERE m.support_ticket_id=t.id AND m.customer_message IS NOT NULL ORDER BY m.id DESC LIMIT 1),t.description) as preview, t.updated_at, CASE WHEN t.admin_read_at IS NULL OR EXISTS (SELECT 1 FROM support_ticket_convs c WHERE c.support_ticket_id=t.id AND (c.customer_message IS NOT NULL OR ((c.admin_id IS NULL OR c.admin_id = 0) AND c.admin_message IS NULL)) AND c.id > COALESCE(t.admin_read_message_id,0)) THEN 1 ELSE 0 END as unread from "support_tickets" as "t" left join "users" as "u" on "u"."id" = "t"."customer_id" where ("t"."purpose" is null or "t"."purpose" != account_activation)) union all select * from (select 'vendor' as kind, t.id, COALESCE(s.f_name,'') || ' ' || COALESCE(s.l_name,'') as sender, 'vendor' as audience, t.subject, COALESCE((SELECT body FROM vendor_support_messages m WHERE m.vendor_support_thread_id=t.id ORDER BY m.id DESC LIMIT 1),'') as preview, t.updated_at, 0 as unread from "vendor_support_threads" as "t" left join "sellers" as "s" on "s"."id" = "t"."seller_id") union all select * from (select 'chat' as kind, c.user_id as id, MAX(COALESCE(u.f_name,'') || ' ' || COALESCE(u.l_name,'')) as sender, 'customer' as audience, '' as subject, COALESCE((SELECT message FROM chattings m WHERE m.user_id=c.user_id AND m.admin_id=0 ORDER BY m.id DESC LIMIT 1),'') as preview, MAX(c.created_at) as updated_at, SUM(CASE WHEN c.sent_by_customer = 1 AND c.seen_by_admin = 0 THEN 1 ELSE 0 END) as unread from "chattings" as "c" left join "users" as "u" on "u"."id" = "c"."user_id" where "c"."admin_id" = 0 and "c"."user_id" is not null group by "c"."user_id")) as "conversations")
/home/sigma-eg/git/sigma-release-qa.LaQbDf/vendor/laravel/framework/src/Illuminate/Database/Connection.php:838
/home/sigma-eg/git/sigma-release-qa.LaQbDf/vendor/laravel/framework/src/Illuminate/Database/Connection.php:794
/home/sigma-eg/git/sigma-release-qa.LaQbDf/vendor/laravel/framework/src/Illuminate/Database/Connection.php:411
```

## Tests\Unit\ApprovedVendorEarningTest::test_only_approved_procurement_tax_and_vendor_shipping_are_held_once

```text
Tests\Unit\ApprovedVendorEarningTest::test_only_approved_procurement_tax_and_vendor_shipping_are_held_once
Failed asserting that 300.0 is identical to 185.0.
/home/sigma-eg/git/sigma-release-qa.LaQbDf/tests/Unit/ApprovedVendorEarningTest.php:46
```

## Tests\Unit\CustomerEmptyCartTest::test_a_signed_in_customer_can_open_an_empty_cart_without_being_sent_to_login

```text
Tests\Unit\CustomerEmptyCartTest::test_a_signed_in_customer_can_open_an_empty_cart_without_being_sent_to_login
Illuminate\Database\QueryException: SQLSTATE[HY000]: General error: 1 no such table: business_settings (Connection: sqlite, Database: :memory:, SQL: select * from "business_settings")
/home/sigma-eg/git/sigma-release-qa.LaQbDf/vendor/laravel/framework/src/Illuminate/Database/Connection.php:838
/home/sigma-eg/git/sigma-release-qa.LaQbDf/vendor/laravel/framework/src/Illuminate/Database/Connection.php:794
/home/sigma-eg/git/sigma-release-qa.LaQbDf/vendor/laravel/framework/src/Illuminate/Database/Connection.php:411
```

## Tests\Unit\CustomerEmptyCartTest::test_a_guest_can_view_an_empty_cart_when_guest_checkout_is_disabled

```text
Tests\Unit\CustomerEmptyCartTest::test_a_guest_can_view_an_empty_cart_when_guest_checkout_is_disabled
Illuminate\Database\QueryException: SQLSTATE[HY000]: General error: 1 no such table: business_settings (Connection: sqlite, Database: :memory:, SQL: select * from "business_settings")
/home/sigma-eg/git/sigma-release-qa.LaQbDf/vendor/laravel/framework/src/Illuminate/Database/Connection.php:838
/home/sigma-eg/git/sigma-release-qa.LaQbDf/vendor/laravel/framework/src/Illuminate/Database/Connection.php:794
/home/sigma-eg/git/sigma-release-qa.LaQbDf/vendor/laravel/framework/src/Illuminate/Database/Connection.php:411
```

## Tests\Unit\CustomerInsuranceBalanceServiceTest::test_verified_insurance_deposit_is_idempotent_and_does_not_credit_purchase_wallet

```text
Tests\Unit\CustomerInsuranceBalanceServiceTest::test_verified_insurance_deposit_is_idempotent_and_does_not_credit_purchase_wallet
Illuminate\Database\QueryException: SQLSTATE[HY000]: General error: 1 no such table: customer_legacy_payment_receipts (Connection: sqlite, Database: :memory:, SQL: insert or ignore into "customer_legacy_payment_receipts" ("reference", "customer_id", "amount", "currency_code", "snapshot", "received_at") values (insurance-deposit-deposit-123, 1, 75, USD, {"payment_id":"deposit-123","payment_method":"stripe","base_amount":75}, 2026-11-04 18:40:46))
/home/sigma-eg/git/sigma-release-qa.LaQbDf/vendor/laravel/framework/src/Illuminate/Database/Connection.php:838
/home/sigma-eg/git/sigma-release-qa.LaQbDf/vendor/laravel/framework/src/Illuminate/Database/Connection.php:794
/home/sigma-eg/git/sigma-release-qa.LaQbDf/vendor/laravel/framework/src/Illuminate/Database/Connection.php:597
```

## Tests\Unit\CustomerInsuranceBalanceServiceTest::test_insurance_applies_only_above_the_admin_threshold

```text
Tests\Unit\CustomerInsuranceBalanceServiceTest::test_insurance_applies_only_above_the_admin_threshold
Failed asserting that false is true.
/home/sigma-eg/git/sigma-release-qa.LaQbDf/tests/Unit/CustomerInsuranceBalanceServiceTest.php:136
```

## Tests\Unit\CustomerInsuranceBalanceServiceTest::test_zero_threshold_means_every_positive_order_and_never_disables_insurance

```text
Tests\Unit\CustomerInsuranceBalanceServiceTest::test_zero_threshold_means_every_positive_order_and_never_disables_insurance
Failed asserting that false is true.
/home/sigma-eg/git/sigma-release-qa.LaQbDf/tests/Unit/CustomerInsuranceBalanceServiceTest.php:150
```

## Tests\Unit\CustomerInsuranceBalanceServiceTest::test_percentage_and_fixed_customer_insurance_keep_the_same_threshold_contract

```text
Tests\Unit\CustomerInsuranceBalanceServiceTest::test_percentage_and_fixed_customer_insurance_keep_the_same_threshold_contract
Failed asserting that 0.0 is identical to 75.0.
/home/sigma-eg/git/sigma-release-qa.LaQbDf/tests/Unit/CustomerInsuranceBalanceServiceTest.php:160
```

## Tests\Unit\CustomerInsuranceBalanceServiceTest::test_paid_insurance_remains_held_until_maturity_then_becomes_insurance_only_balance

```text
Tests\Unit\CustomerInsuranceBalanceServiceTest::test_paid_insurance_remains_held_until_maturity_then_becomes_insurance_only_balance
Illuminate\Database\QueryException: SQLSTATE[HY000]: General error: 1 table users has no column named canonical_phone (Connection: sqlite, Database: :memory:, SQL: insert into "users" ("name", "email", "phone", "canonical_phone", "wallet_balance", "updated_at", "created_at") values (Insurance Test Customer, insurance@example.com, 01000000000, 01000000000, 500, 2026-08-10 10:00:00, 2026-08-10 10:00:00))
/home/sigma-eg/git/sigma-release-qa.LaQbDf/vendor/laravel/framework/src/Illuminate/Database/Connection.php:838
/home/sigma-eg/git/sigma-release-qa.LaQbDf/vendor/laravel/framework/src/Illuminate/Database/Connection.php:794
/home/sigma-eg/git/sigma-release-qa.LaQbDf/vendor/laravel/framework/src/Illuminate/Database/Connection.php:573
```

## Tests\Unit\CustomerInsuranceBalanceServiceTest::test_matured_balance_cannot_pay_products_and_can_pay_insurance_idempotently

```text
Tests\Unit\CustomerInsuranceBalanceServiceTest::test_matured_balance_cannot_pay_products_and_can_pay_insurance_idempotently
Illuminate\Database\QueryException: SQLSTATE[HY000]: General error: 1 table users has no column named canonical_phone (Connection: sqlite, Database: :memory:, SQL: insert into "users" ("name", "email", "phone", "canonical_phone", "wallet_balance", "updated_at", "created_at") values (Insurance Test Customer, insurance@example.com, 01000000000, 01000000000, 500, 2026-08-10 10:00:00, 2026-08-10 10:00:00))
/home/sigma-eg/git/sigma-release-qa.LaQbDf/vendor/laravel/framework/src/Illuminate/Database/Connection.php:838
/home/sigma-eg/git/sigma-release-qa.LaQbDf/vendor/laravel/framework/src/Illuminate/Database/Connection.php:794
/home/sigma-eg/git/sigma-release-qa.LaQbDf/vendor/laravel/framework/src/Illuminate/Database/Connection.php:573
```

## Tests\Unit\CustomerPostPurchaseInsuranceFlowTest::test_purchase_payment_and_insurance_are_two_distinct_steps_and_references

```text
Tests\Unit\CustomerPostPurchaseInsuranceFlowTest::test_purchase_payment_and_insurance_are_two_distinct_steps_and_references
Failed asserting that null is identical to 'purchase_payment_pending'.
/home/sigma-eg/git/sigma-release-qa.LaQbDf/tests/Unit/CustomerPostPurchaseInsuranceFlowTest.php:125
```

## Tests\Unit\CustomerPostPurchaseInsuranceFlowTest::test_insurance_wallet_payment_moves_order_to_admin_review_without_touching_purchase_wallet

```text
Tests\Unit\CustomerPostPurchaseInsuranceFlowTest::test_insurance_wallet_payment_moves_order_to_admin_review_without_touching_purchase_wallet
TypeError: App\Services\CustomerPostPurchaseInsuranceService::payFromInsuranceBalance(): Argument #1 ($insurance) must be of type App\Models\OrderInsurance, null given, called in /home/sigma-eg/git/sigma-release-qa.LaQbDf/tests/Unit/CustomerPostPurchaseInsuranceFlowTest.php on line 147
/home/sigma-eg/git/sigma-release-qa.LaQbDf/app/Services/CustomerPostPurchaseInsuranceService.php:137
/home/sigma-eg/git/sigma-release-qa.LaQbDf/tests/Unit/CustomerPostPurchaseInsuranceFlowTest.php:147
```

## Tests\Unit\CustomerPostPurchaseInsuranceFlowTest::test_offline_evidence_can_be_rejected_and_resubmitted_without_releasing_order

```text
Tests\Unit\CustomerPostPurchaseInsuranceFlowTest::test_offline_evidence_can_be_rejected_and_resubmitted_without_releasing_order
TypeError: App\Services\CustomerPostPurchaseInsuranceService::submitOfflinePayment(): Argument #1 ($insurance) must be of type App\Models\OrderInsurance, null given, called in /home/sigma-eg/git/sigma-release-qa.LaQbDf/tests/Unit/CustomerPostPurchaseInsuranceFlowTest.php on line 162
/home/sigma-eg/git/sigma-release-qa.LaQbDf/app/Services/CustomerPostPurchaseInsuranceService.php:190
/home/sigma-eg/git/sigma-release-qa.LaQbDf/tests/Unit/CustomerPostPurchaseInsuranceFlowTest.php:162
```

## Tests\Unit\CustomerPostPurchaseInsuranceFlowTest::test_payment_method_cannot_change_while_offline_evidence_is_under_review

```text
Tests\Unit\CustomerPostPurchaseInsuranceFlowTest::test_payment_method_cannot_change_while_offline_evidence_is_under_review
TypeError: App\Services\CustomerPostPurchaseInsuranceService::submitOfflinePayment(): Argument #1 ($insurance) must be of type App\Models\OrderInsurance, null given, called in /home/sigma-eg/git/sigma-release-qa.LaQbDf/tests/Unit/CustomerPostPurchaseInsuranceFlowTest.php on line 176
/home/sigma-eg/git/sigma-release-qa.LaQbDf/app/Services/CustomerPostPurchaseInsuranceService.php:190
/home/sigma-eg/git/sigma-release-qa.LaQbDf/tests/Unit/CustomerPostPurchaseInsuranceFlowTest.php:176
```

## Tests\Unit\CustomerPostPurchaseInsuranceFlowTest::test_support_ticket_is_linked_once_to_the_insurance_claim

```text
Tests\Unit\CustomerPostPurchaseInsuranceFlowTest::test_support_ticket_is_linked_once_to_the_insurance_claim
TypeError: App\Services\CustomerPostPurchaseInsuranceService::openSupportTicket(): Argument #1 ($insurance) must be of type App\Models\OrderInsurance, null given, called in /home/sigma-eg/git/sigma-release-qa.LaQbDf/tests/Unit/CustomerPostPurchaseInsuranceFlowTest.php on line 195
/home/sigma-eg/git/sigma-release-qa.LaQbDf/app/Services/CustomerPostPurchaseInsuranceService.php:250
/home/sigma-eg/git/sigma-release-qa.LaQbDf/tests/Unit/CustomerPostPurchaseInsuranceFlowTest.php:195
```

## Tests\Unit\CustomerPostPurchaseInsuranceFlowTest::test_decline_schedules_purchase_refund_then_credits_purchase_wallet_once

```text
Tests\Unit\CustomerPostPurchaseInsuranceFlowTest::test_decline_schedules_purchase_refund_then_credits_purchase_wallet_once
TypeError: App\Services\CustomerPostPurchaseInsuranceService::decline(): Argument #1 ($insurance) must be of type App\Models\OrderInsurance, null given, called in /home/sigma-eg/git/sigma-release-qa.LaQbDf/tests/Unit/CustomerPostPurchaseInsuranceFlowTest.php on line 210
/home/sigma-eg/git/sigma-release-qa.LaQbDf/app/Services/CustomerPostPurchaseInsuranceService.php:276
/home/sigma-eg/git/sigma-release-qa.LaQbDf/tests/Unit/CustomerPostPurchaseInsuranceFlowTest.php:210
```

## Tests\Unit\CustomerPostPurchaseInsuranceFlowTest::test_paid_insurance_cannot_be_declined

```text
Tests\Unit\CustomerPostPurchaseInsuranceFlowTest::test_paid_insurance_cannot_be_declined
TypeError: App\Services\CustomerPostPurchaseInsuranceService::markPaid(): Argument #1 ($insurance) must be of type App\Models\OrderInsurance, null given, called in /home/sigma-eg/git/sigma-release-qa.LaQbDf/tests/Unit/CustomerPostPurchaseInsuranceFlowTest.php on line 227
/home/sigma-eg/git/sigma-release-qa.LaQbDf/app/Services/CustomerPostPurchaseInsuranceService.php:148
/home/sigma-eg/git/sigma-release-qa.LaQbDf/tests/Unit/CustomerPostPurchaseInsuranceFlowTest.php:227
```

## Tests\Unit\HomepageSectionBuilderPhaseSixTest::test_both_public_templates_and_theme_activation_use_the_builder_contract

```text
Tests\Unit\HomepageSectionBuilderPhaseSixTest::test_both_public_templates_and_theme_activation_use_the_builder_contract
Failed asserting that '<?php\n
\n
namespace App\Http\Controllers\Web;\n
\n
```

## Tests\Unit\IdentityAndSupportBatchTest::test_customer_activation_ticket_is_created_once_and_admin_can_approve_it

```text
Tests\Unit\IdentityAndSupportBatchTest::test_customer_activation_ticket_is_created_once_and_admin_can_approve_it
ErrorException: Attempt to read property "id" on null
/home/sigma-eg/git/sigma-release-qa.LaQbDf/vendor/laravel/framework/src/Illuminate/Foundation/Bootstrap/HandleExceptions.php:258
/home/sigma-eg/git/sigma-release-qa.LaQbDf/tests/Unit/IdentityAndSupportBatchTest.php:136
```

## Tests\Unit\IdentityAndSupportBatchTest::test_vendor_repository_can_find_the_visible_m_reference

```text
Tests\Unit\IdentityAndSupportBatchTest::test_vendor_repository_can_find_the_visible_m_reference
Failed asserting that two strings are identical.
--- Expected
+++ Actual
@@ @@
```

## Tests\Unit\InsuranceRulesAndIncentivesTest::test_welcome_credit_and_premium_discount_are_separate_idempotent_records

```text
Tests\Unit\InsuranceRulesAndIncentivesTest::test_welcome_credit_and_premium_discount_are_separate_idempotent_records
Failed asserting that 0.0 is identical to 50.0.
/home/sigma-eg/git/sigma-release-qa.LaQbDf/tests/Unit/InsuranceRulesAndIncentivesTest.php:141
```

## Tests\Unit\InsuranceRulesAndIncentivesTest::test_selected_scope_does_not_insure_new_customers_until_they_are_included

```text
Tests\Unit\InsuranceRulesAndIncentivesTest::test_selected_scope_does_not_insure_new_customers_until_they_are_included
Failed asserting that false is true.
/home/sigma-eg/git/sigma-release-qa.LaQbDf/tests/Unit/InsuranceRulesAndIncentivesTest.php:185
```

## Tests\Unit\InsuranceRulesAndIncentivesTest::test_saving_one_insurance_menu_does_not_change_the_other

```text
Tests\Unit\InsuranceRulesAndIncentivesTest::test_saving_one_insurance_menu_does_not_change_the_other
Illuminate\Database\QueryException: SQLSTATE[HY000]: General error: 1 no such table: post_purchase_invoices (Connection: sqlite, Database: :memory:, SQL: select * from "post_purchase_invoices" where "status" = pending and "tax_amount" > 0 and "id" is not null order by "id" asc limit 100)
/home/sigma-eg/git/sigma-release-qa.LaQbDf/vendor/laravel/framework/src/Illuminate/Database/Connection.php:838
/home/sigma-eg/git/sigma-release-qa.LaQbDf/vendor/laravel/framework/src/Illuminate/Database/Connection.php:794
/home/sigma-eg/git/sigma-release-qa.LaQbDf/vendor/laravel/framework/src/Illuminate/Database/Connection.php:411
```

## Tests\Unit\InsuranceRulesAndIncentivesTest::test_created_order_keeps_rule_and_discount_snapshots_after_admin_changes

```text
Tests\Unit\InsuranceRulesAndIncentivesTest::test_created_order_keeps_rule_and_discount_snapshots_after_admin_changes
DomainException: customer_security_deposits_retired
/home/sigma-eg/git/sigma-release-qa.LaQbDf/app/Services/OrderInsuranceService.php:101
/home/sigma-eg/git/sigma-release-qa.LaQbDf/tests/Unit/InsuranceRulesAndIncentivesTest.php:229
```

## Tests\Unit\InsuranceWalletSeparationTest::test_purchase_wallet_and_insurance_wallet_policies_are_one_way

```text
Tests\Unit\InsuranceWalletSeparationTest::test_purchase_wallet_and_insurance_wallet_policies_are_one_way
Failed asserting that two arrays are identical.
--- Expected
+++ Actual
@@ @@
```

## Tests\Unit\InsuranceWalletSeparationTest::test_customer_hold_partial_release_confiscation_and_reversal_are_audited_and_idempotent

```text
Tests\Unit\InsuranceWalletSeparationTest::test_customer_hold_partial_release_confiscation_and_reversal_are_audited_and_idempotent
DomainException: تم إيقاف تأمين العملاء. تتم مطابقة المبالغ القديمة من الأرشيف.
/home/sigma-eg/git/sigma-release-qa.LaQbDf/app/Services/InsuranceBalanceGovernanceService.php:385
/home/sigma-eg/git/sigma-release-qa.LaQbDf/app/Services/InsuranceBalanceGovernanceService.php:219
/home/sigma-eg/git/sigma-release-qa.LaQbDf/app/Services/InsuranceBalanceGovernanceService.php:30
```

## Tests\Unit\InsuranceWalletSeparationTest::test_customer_paid_insurance_record_can_be_partially_confiscated_before_maturity_without_double_debit

```text
Tests\Unit\InsuranceWalletSeparationTest::test_customer_paid_insurance_record_can_be_partially_confiscated_before_maturity_without_double_debit
DomainException: تم إيقاف تأمين العملاء. تتم مطابقة المبالغ القديمة من الأرشيف.
/home/sigma-eg/git/sigma-release-qa.LaQbDf/app/Services/InsuranceBalanceGovernanceService.php:59
/home/sigma-eg/git/sigma-release-qa.LaQbDf/tests/Unit/InsuranceWalletSeparationTest.php:102
```

## Tests\Unit\InsuranceWalletSeparationTest::test_released_customer_insurance_record_debits_and_reverses_the_insurance_wallet_exactly_once

```text
Tests\Unit\InsuranceWalletSeparationTest::test_released_customer_insurance_record_debits_and_reverses_the_insurance_wallet_exactly_once
DomainException: تم إيقاف تأمين العملاء. تتم مطابقة المبالغ القديمة من الأرشيف.
/home/sigma-eg/git/sigma-release-qa.LaQbDf/app/Services/InsuranceBalanceGovernanceService.php:59
/home/sigma-eg/git/sigma-release-qa.LaQbDf/tests/Unit/InsuranceWalletSeparationTest.php:129
```

## Tests\Unit\OfflineSecondStageTest::test_submitted_offline_order_gets_one_second_invoice_without_confirming_first_payment

```text
Tests\Unit\OfflineSecondStageTest::test_submitted_offline_order_gets_one_second_invoice_without_confirming_first_payment
Failed asserting that actual size 0 matches expected size 1.
/home/sigma-eg/git/sigma-release-qa.LaQbDf/tests/Unit/OfflineSecondStageTest.php:54
```

## Tests\Unit\OperationsCatalogTest::test_both_public_templates_and_theme_activation_use_the_builder_contract

```text
Tests\Unit\OperationsCatalogTest::test_both_public_templates_and_theme_activation_use_the_builder_contract
Failed asserting that '<?php\n
\n
namespace App\Http\Controllers\Web;\n
\n
```

## Tests\Unit\OperationsCatalogTest::test_integrated_blade_views_compile_without_php_syntax_errors

```text
Tests\Unit\OperationsCatalogTest::test_integrated_blade_views_compile_without_php_syntax_errors
ErrorException: file_get_contents(/home/sigma-eg/git/sigma-release-qa.LaQbDf/resources/themes/theme_sigma_care/theme-views/home.blade.php): Failed to open stream: No such file or directory
/home/sigma-eg/git/sigma-release-qa.LaQbDf/vendor/laravel/framework/src/Illuminate/Foundation/Bootstrap/HandleExceptions.php:258
/home/sigma-eg/git/sigma-release-qa.LaQbDf/tests/Unit/OperationsCatalogTest.php:532
```

## Tests\Unit\OrderCommercePhaseZeroTest::test_customer_insurance_contract_separates_purchase_and_insurance_values

```text
Tests\Unit\OrderCommercePhaseZeroTest::test_customer_insurance_contract_separates_purchase_and_insurance_values
Failed asserting that false is true.
/home/sigma-eg/git/sigma-release-qa.LaQbDf/tests/Unit/OrderCommercePhaseZeroTest.php:123
```

## Tests\Unit\OrderCommercePhaseZeroTest::test_dashboard_contains_fixed_shortcuts_for_the_merged_admin_centers

```text
Tests\Unit\OrderCommercePhaseZeroTest::test_dashboard_contains_fixed_shortcuts_for_the_merged_admin_centers
Failed asserting that '@php\n
    $isSuper = (int) auth('admin')->user()->admin_role_id === 1;\n
    $shortcuts = [\n
        ['group'=>'operations','permission'=>'people','label'=>'customers','icon'=>'fi fi-sr-users','route'=>'admin.customer.list','params'=>[]],\n
```

## Tests\Unit\OrderPaymentSummaryTest::test_purchase_paid_does_not_hide_unpaid_insurance_and_tax

```text
Tests\Unit\OrderPaymentSummaryTest::test_purchase_paid_does_not_hide_unpaid_insurance_and_tax
Failed asserting that false is true.
/home/sigma-eg/git/sigma-release-qa.LaQbDf/tests/Unit/OrderPaymentSummaryTest.php:15
```

## Tests\Unit\OrderPaymentSummaryTest::test_submitted_proof_is_shown_as_under_review_not_as_paid

```text
Tests\Unit\OrderPaymentSummaryTest::test_submitted_proof_is_shown_as_under_review_not_as_paid
Failed asserting that false is true.
/home/sigma-eg/git/sigma-release-qa.LaQbDf/tests/Unit/OrderPaymentSummaryTest.php:25
```

## Tests\Unit\OrderPaymentSummaryTest::test_insurance_balance_payment_does_not_mark_remaining_tax_paid

```text
Tests\Unit\OrderPaymentSummaryTest::test_insurance_balance_payment_does_not_mark_remaining_tax_paid
Failed asserting that null is identical to 'payment_stage_paid'.
/home/sigma-eg/git/sigma-release-qa.LaQbDf/tests/Unit/OrderPaymentSummaryTest.php:32
```

## Tests\Unit\OrderPaymentSummaryTest::test_fully_paid_second_invoice_clears_warning

```text
Tests\Unit\OrderPaymentSummaryTest::test_fully_paid_second_invoice_clears_warning
Failed asserting that null is identical to 'payment_stage_paid'.
/home/sigma-eg/git/sigma-release-qa.LaQbDf/tests/Unit/OrderPaymentSummaryTest.php:42
```

## Tests\Unit\OrderPaymentSummaryTest::test_second_invoice_not_yet_calculated_is_not_presented_as_complete

```text
Tests\Unit\OrderPaymentSummaryTest::test_second_invoice_not_yet_calculated_is_not_presented_as_complete
Failed asserting that false is true.
/home/sigma-eg/git/sigma-release-qa.LaQbDf/tests/Unit/OrderPaymentSummaryTest.php:51
```

## Tests\Unit\OrderShippingProofServiceTest::test_delivery_upload_completes_without_intermediate_proofs_or_admin_approval

```text
Tests\Unit\OrderShippingProofServiceTest::test_delivery_upload_completes_without_intermediate_proofs_or_admin_approval
Illuminate\Database\QueryException: SQLSTATE[HY000]: General error: 1 no such table: seller_order_insurances (Connection: sqlite, Database: :memory:, SQL: select * from "seller_order_insurances" where "seller_order_insurances"."order_id" = 1 and "seller_order_insurances"."order_id" is not null limit 1)
/home/sigma-eg/git/sigma-release-qa.LaQbDf/vendor/laravel/framework/src/Illuminate/Database/Connection.php:838
/home/sigma-eg/git/sigma-release-qa.LaQbDf/vendor/laravel/framework/src/Illuminate/Database/Connection.php:794
/home/sigma-eg/git/sigma-release-qa.LaQbDf/vendor/laravel/framework/src/Illuminate/Database/Connection.php:411
```

## Tests\Unit\OrderShippingProofServiceTest::test_mobile_proof_uses_request_seller_without_web_login_and_keeps_private_history

```text
Tests\Unit\OrderShippingProofServiceTest::test_mobile_proof_uses_request_seller_without_web_login_and_keeps_private_history
Illuminate\Database\QueryException: SQLSTATE[HY000]: General error: 1 no such table: seller_order_insurances (Connection: sqlite, Database: :memory:, SQL: select * from "seller_order_insurances" where "seller_order_insurances"."order_id" = 1 and "seller_order_insurances"."order_id" is not null limit 1)
/home/sigma-eg/git/sigma-release-qa.LaQbDf/vendor/laravel/framework/src/Illuminate/Database/Connection.php:838
/home/sigma-eg/git/sigma-release-qa.LaQbDf/vendor/laravel/framework/src/Illuminate/Database/Connection.php:794
/home/sigma-eg/git/sigma-release-qa.LaQbDf/vendor/laravel/framework/src/Illuminate/Database/Connection.php:411
```

## Tests\Unit\OrderShippingProofServiceTest::test_completion_failure_rolls_back_proof_status_and_file

```text
Tests\Unit\OrderShippingProofServiceTest::test_completion_failure_rolls_back_proof_status_and_file
Illuminate\Database\QueryException: SQLSTATE[HY000]: General error: 1 no such table: seller_order_insurances (Connection: sqlite, Database: :memory:, SQL: select * from "seller_order_insurances" where "seller_order_insurances"."order_id" = 1 and "seller_order_insurances"."order_id" is not null limit 1)
/home/sigma-eg/git/sigma-release-qa.LaQbDf/vendor/laravel/framework/src/Illuminate/Database/Connection.php:838
/home/sigma-eg/git/sigma-release-qa.LaQbDf/vendor/laravel/framework/src/Illuminate/Database/Connection.php:794
/home/sigma-eg/git/sigma-release-qa.LaQbDf/vendor/laravel/framework/src/Illuminate/Database/Connection.php:411
```

## Tests\Unit\OrderShippingProofServiceTest::test_seller_uploads_private_proofs_in_order_and_admin_approves_delivery

```text
Tests\Unit\OrderShippingProofServiceTest::test_seller_uploads_private_proofs_in_order_and_admin_approves_delivery
Illuminate\Database\QueryException: SQLSTATE[HY000]: General error: 1 no such table: seller_order_insurances (Connection: sqlite, Database: :memory:, SQL: select * from "seller_order_insurances" where "seller_order_insurances"."order_id" = 1 and "seller_order_insurances"."order_id" is not null limit 1)
/home/sigma-eg/git/sigma-release-qa.LaQbDf/vendor/laravel/framework/src/Illuminate/Database/Connection.php:838
/home/sigma-eg/git/sigma-release-qa.LaQbDf/vendor/laravel/framework/src/Illuminate/Database/Connection.php:794
/home/sigma-eg/git/sigma-release-qa.LaQbDf/vendor/laravel/framework/src/Illuminate/Database/Connection.php:411
```

## Tests\Unit\PaymentReviewWorkspaceTest::test_deposits_and_subscriptions_share_counts_filters_and_pagination

```text
Tests\Unit\PaymentReviewWorkspaceTest::test_deposits_and_subscriptions_share_counts_filters_and_pagination
Error: Undefined constant App\Models\SellerPackageSubscription::STATUS_SCHEDULED
/home/sigma-eg/git/sigma-release-qa.LaQbDf/vendor/laravel/framework/src/Illuminate/Conditionable/Traits/Conditionable.php:34
/home/sigma-eg/git/sigma-release-qa.LaQbDf/app/Http/Controllers/Admin/PaymentReviewController.php:370
/home/sigma-eg/git/sigma-release-qa.LaQbDf/app/Http/Controllers/Admin/PaymentReviewController.php:51
```

## Tests\Unit\PaymentReviewWorkspaceTest::test_notifications_and_old_links_target_unified_deposit

```text
Tests\Unit\PaymentReviewWorkspaceTest::test_notifications_and_old_links_target_unified_deposit
Error: Undefined constant App\Models\SellerPackageSubscription::STATUS_SCHEDULED
/home/sigma-eg/git/sigma-release-qa.LaQbDf/vendor/laravel/framework/src/Illuminate/Conditionable/Traits/Conditionable.php:34
/home/sigma-eg/git/sigma-release-qa.LaQbDf/app/Http/Controllers/Admin/PaymentReviewController.php:370
/home/sigma-eg/git/sigma-release-qa.LaQbDf/app/Http/Controllers/Admin/PaymentReviewController.php:51
```

## Tests\Unit\PaymentReviewWorkspaceTest::test_drawer_and_filters_render_arabic_without_exposing_storage_paths

```text
Tests\Unit\PaymentReviewWorkspaceTest::test_drawer_and_filters_render_arabic_without_exposing_storage_paths
Error: Undefined constant App\Models\SellerPackageSubscription::STATUS_SCHEDULED
/home/sigma-eg/git/sigma-release-qa.LaQbDf/vendor/laravel/framework/src/Illuminate/Conditionable/Traits/Conditionable.php:34
/home/sigma-eg/git/sigma-release-qa.LaQbDf/app/Http/Controllers/Admin/PaymentReviewController.php:370
/home/sigma-eg/git/sigma-release-qa.LaQbDf/app/Http/Controllers/Admin/PaymentReviewController.php:51
```

## Tests\Unit\PaymentReviewWorkspaceTest::test_settings_show_only_offline_methods_and_respect_management_permissions

```text
Tests\Unit\PaymentReviewWorkspaceTest::test_settings_show_only_offline_methods_and_respect_management_permissions
BadMethodCallException: Method App\Http\Controllers\Admin\Vendor\SellerBalanceFundingSettingsController::pageData does not exist.
/home/sigma-eg/git/sigma-release-qa.LaQbDf/vendor/laravel/framework/src/Illuminate/Routing/Controller.php:68
/home/sigma-eg/git/sigma-release-qa.LaQbDf/app/Http/Controllers/Admin/PaymentReviewController.php:42
/home/sigma-eg/git/sigma-release-qa.LaQbDf/tests/Unit/PaymentReviewWorkspaceTest.php:73
```

## Tests\Unit\PaymentReviewWorkspaceTest::test_internal_payment_identifiers_stay_in_details_without_expanding_table_rows

```text
Tests\Unit\PaymentReviewWorkspaceTest::test_internal_payment_identifiers_stay_in_details_without_expanding_table_rows
Error: Undefined constant App\Models\SellerPackageSubscription::STATUS_SCHEDULED
/home/sigma-eg/git/sigma-release-qa.LaQbDf/vendor/laravel/framework/src/Illuminate/Conditionable/Traits/Conditionable.php:34
/home/sigma-eg/git/sigma-release-qa.LaQbDf/app/Http/Controllers/Admin/PaymentReviewController.php:370
/home/sigma-eg/git/sigma-release-qa.LaQbDf/app/Http/Controllers/Admin/PaymentReviewController.php:51
```

## Tests\Unit\PostPurchaseInvoicePayabilityTest::test_second_invoice_is_payable_after_first_payment_proof_is_submitted

```text
Tests\Unit\PostPurchaseInvoicePayabilityTest::test_second_invoice_is_payable_after_first_payment_proof_is_submitted
Failed asserting that false is true.
/home/sigma-eg/git/sigma-release-qa.LaQbDf/tests/Unit/PostPurchaseInvoicePayabilityTest.php:24
```

## Tests\Unit\SellerActivationRegistrationApiTest::test_registration_activation_api_returns_banner_ticket_messages_and_accepts_seller_reply

```text
Tests\Unit\SellerActivationRegistrationApiTest::test_registration_activation_api_returns_banner_ticket_messages_and_accepts_seller_reply
Failed asserting that false is true.
/home/sigma-eg/git/sigma-release-qa.LaQbDf/tests/Unit/SellerActivationRegistrationApiTest.php:99
```

## Tests\Unit\SellerActivationServiceTest::test_registration_ticket_is_created_once_with_the_required_automatic_message

```text
Tests\Unit\SellerActivationServiceTest::test_registration_ticket_is_created_once_with_the_required_automatic_message
ErrorException: Attempt to read property "id" on null
/home/sigma-eg/git/sigma-release-qa.LaQbDf/vendor/laravel/framework/src/Illuminate/Foundation/Bootstrap/HandleExceptions.php:258
/home/sigma-eg/git/sigma-release-qa.LaQbDf/tests/Unit/SellerActivationServiceTest.php:77
```

## Tests\Unit\SellerCommerceRegressionTest::test_product_quota_guard_rejects_a_seller_with_pending_activation

```text
Tests\Unit\SellerCommerceRegressionTest::test_product_quota_guard_rejects_a_seller_with_pending_activation
Expected DomainException was not thrown.
/home/sigma-eg/git/sigma-release-qa.LaQbDf/tests/Unit/SellerCommerceRegressionTest.php:250
/home/sigma-eg/git/sigma-release-qa.LaQbDf/tests/Unit/SellerCommerceRegressionTest.php:207
```

## Tests\Unit\SellerMobileSupportConversationTest::test_device_can_resume_its_own_ticket_without_login_and_creation_is_idempotent

```text
Tests\Unit\SellerMobileSupportConversationTest::test_device_can_resume_its_own_ticket_without_login_and_creation_is_idempotent
Illuminate\Database\QueryException: SQLSTATE[HY000]: General error: 1 table contacts has no column named sender_type (Connection: sqlite, Database: :memory:, SQL: insert into "contacts" ("mobile_support_token_hash", "name", "email", "mobile_number", "message", "sender_type", "subject", "seen", "updated_at", "created_at") values (ffe054fe7ae0cb6dc65c3af9b61d5209f439851db43d0ba5997337df154668eb, Seller, test@example.com, +201000000000, Reset my password, vendor, طلب إعادة تعيين كلمة المرور - تاجر, 0, 2026-11-04 18:41:14, 2026-11-04 18:41:14))
/home/sigma-eg/git/sigma-release-qa.LaQbDf/vendor/laravel/framework/src/Illuminate/Database/Connection.php:838
/home/sigma-eg/git/sigma-release-qa.LaQbDf/vendor/laravel/framework/src/Illuminate/Database/Connection.php:794
/home/sigma-eg/git/sigma-release-qa.LaQbDf/vendor/laravel/framework/src/Illuminate/Database/Connection.php:573
```

## Tests\Unit\SellerMobileSupportConversationTest::test_another_device_cannot_read_ticket_by_knowing_email

```text
Tests\Unit\SellerMobileSupportConversationTest::test_another_device_cannot_read_ticket_by_knowing_email
Illuminate\Database\QueryException: SQLSTATE[HY000]: General error: 1 table contacts has no column named sender_type (Connection: sqlite, Database: :memory:, SQL: insert into "contacts" ("mobile_support_token_hash", "name", "email", "mobile_number", "message", "sender_type", "subject", "seen", "updated_at", "created_at") values (ffe054fe7ae0cb6dc65c3af9b61d5209f439851db43d0ba5997337df154668eb, Seller, test@example.com, 12345, Help, vendor, طلب إعادة تعيين كلمة المرور - تاجر, 0, 2026-11-04 18:41:14, 2026-11-04 18:41:14))
/home/sigma-eg/git/sigma-release-qa.LaQbDf/vendor/laravel/framework/src/Illuminate/Database/Connection.php:838
/home/sigma-eg/git/sigma-release-qa.LaQbDf/vendor/laravel/framework/src/Illuminate/Database/Connection.php:794
/home/sigma-eg/git/sigma-release-qa.LaQbDf/vendor/laravel/framework/src/Illuminate/Database/Connection.php:573
```

## Tests\Unit\SellerProductApiPolicyGuardTest::test_legacy_add_and_update_apis_reject_fewer_than_five_distinct_images

```text
Tests\Unit\SellerProductApiPolicyGuardTest::test_legacy_add_and_update_apis_reject_fewer_than_five_distinct_images
Failed asserting that 200 is identical to 422.
/home/sigma-eg/git/sigma-release-qa.LaQbDf/tests/Unit/SellerProductApiPolicyGuardTest.php:168
```

## Tests\Unit\SellerProductApiPolicyGuardTest::test_current_add_api_rejects_fewer_than_five_distinct_images

```text
Tests\Unit\SellerProductApiPolicyGuardTest::test_current_add_api_rejects_fewer_than_five_distinct_images
Illuminate\Database\QueryException: SQLSTATE[HY000]: General error: 1 no such table: system_tax_setups (Connection: sqlite, Database: :memory:, SQL: select * from "system_tax_setups" where "is_active" = 1 and "tax_payer" = vendor and "is_default" = 1 limit 1)
/home/sigma-eg/git/sigma-release-qa.LaQbDf/vendor/laravel/framework/src/Illuminate/Database/Connection.php:838
/home/sigma-eg/git/sigma-release-qa.LaQbDf/vendor/laravel/framework/src/Illuminate/Database/Connection.php:794
/home/sigma-eg/git/sigma-release-qa.LaQbDf/vendor/laravel/framework/src/Illuminate/Database/Connection.php:411
```

## Tests\Unit\SellerProductApiPolicyGuardTest::test_current_update_api_rejects_fewer_than_five_distinct_images

```text
Tests\Unit\SellerProductApiPolicyGuardTest::test_current_update_api_rejects_fewer_than_five_distinct_images
Illuminate\Database\QueryException: SQLSTATE[HY000]: General error: 1 no such table: system_tax_setups (Connection: sqlite, Database: :memory:, SQL: select * from "system_tax_setups" where "is_active" = 1 and "tax_payer" = vendor and "is_default" = 1 limit 1)
/home/sigma-eg/git/sigma-release-qa.LaQbDf/vendor/laravel/framework/src/Illuminate/Database/Connection.php:838
/home/sigma-eg/git/sigma-release-qa.LaQbDf/vendor/laravel/framework/src/Illuminate/Database/Connection.php:794
/home/sigma-eg/git/sigma-release-qa.LaQbDf/vendor/laravel/framework/src/Illuminate/Database/Connection.php:411
```

## Tests\Unit\SellerRegistrationRequestTest::test_first_registration_stage_requires_only_email_phone_and_password

```text
Tests\Unit\SellerRegistrationRequestTest::test_first_registration_stage_requires_only_email_phone_and_password
Failed asserting that false is true.
/home/sigma-eg/git/sigma-release-qa.LaQbDf/tests/Unit/SellerRegistrationRequestTest.php:39
```

## Tests\Unit\SellerRegistrationVerificationModeTest::test_default_mode_uses_support_ticket_without_otp

```text
Tests\Unit\SellerRegistrationVerificationModeTest::test_default_mode_uses_support_ticket_without_otp
Failed asserting that false is true.
/home/sigma-eg/git/sigma-release-qa.LaQbDf/tests/Unit/SellerRegistrationVerificationModeTest.php:41
```

## Tests\Unit\SellerRegistrationVerificationModeTest::test_each_configured_mode_exposes_its_required_verification_steps

```text
Tests\Unit\SellerRegistrationVerificationModeTest::test_each_configured_mode_exposes_its_required_verification_steps
Failed asserting that false is true.
/home/sigma-eg/git/sigma-release-qa.LaQbDf/tests/Unit/SellerRegistrationVerificationModeTest.php:61
```

## Tests\Unit\SellerRestrictedOrdersPhaseFourTest::test_routes_and_application_have_a_dedicated_pending_insurance_inbox

```text
Tests\Unit\SellerRestrictedOrdersPhaseFourTest::test_routes_and_application_have_a_dedicated_pending_insurance_inbox
ErrorException: file_get_contents(E:/project/vendor-app-main/lib/utill/app_constants.dart): Failed to open stream: No such file or directory
/home/sigma-eg/git/sigma-release-qa.LaQbDf/vendor/laravel/framework/src/Illuminate/Foundation/Bootstrap/HandleExceptions.php:258
/home/sigma-eg/git/sigma-release-qa.LaQbDf/tests/Unit/SellerRestrictedOrdersPhaseFourTest.php:98
```

## Tests\Unit\SellerRestrictedOrdersPhaseFourTest::test_company_shipping_statuses_are_protected_in_web_api_and_application

```text
Tests\Unit\SellerRestrictedOrdersPhaseFourTest::test_company_shipping_statuses_are_protected_in_web_api_and_application
ErrorException: file_get_contents(E:/project/vendor-app-main/lib/features/order_details/widgets/seller_shipping_assignment_widget.dart): Failed to open stream: No such file or directory
/home/sigma-eg/git/sigma-release-qa.LaQbDf/vendor/laravel/framework/src/Illuminate/Foundation/Bootstrap/HandleExceptions.php:258
/home/sigma-eg/git/sigma-release-qa.LaQbDf/tests/Unit/SellerRestrictedOrdersPhaseFourTest.php:107
```

## Tests\Unit\StageN2N3ContractTest::test_n2_dashboard_uses_one_server_contract_for_web_and_vendor_app

```text
Tests\Unit\StageN2N3ContractTest::test_n2_dashboard_uses_one_server_contract_for_web_and_vendor_app
Could not read ../Vendor app/lib/utill/app_constants.dart
/home/sigma-eg/git/sigma-release-qa.LaQbDf/tests/Unit/StageN2N3ContractTest.php:99
/home/sigma-eg/git/sigma-release-qa.LaQbDf/tests/Unit/StageN2N3ContractTest.php:14
```

## Tests\Unit\StageN2N3ContractTest::test_n3_ordinary_product_creation_has_no_package_or_insurance_gate

```text
Tests\Unit\StageN2N3ContractTest::test_n3_ordinary_product_creation_has_no_package_or_insurance_gate
Failed asserting that '<?php\n
\n
namespace App\Services;\n
\n
```

## Tests\Unit\StageN2N3ContractTest::test_n3_product_lifecycle_and_optional_measurement_contract_is_server_enforced

```text
Tests\Unit\StageN2N3ContractTest::test_n3_product_lifecycle_and_optional_measurement_contract_is_server_enforced
Failed asserting that '<?php\n
\n
namespace App\Http\Requests;\n
\n
```

## Tests\Unit\StageN2N3ContractTest::test_n3_admin_review_decision_is_audited_and_reason_protected

```text
Tests\Unit\StageN2N3ContractTest::test_n3_admin_review_decision_is_audited_and_reason_protected
Failed asserting that '@extends('layouts.admin.app')\n
\n
@section('title', translate('product_Preview'))\n
\n
```

## Tests\Unit\StageN4PostPurchaseInvoiceContractTest::test_second_invoice_has_an_immutable_tax_and_insurance_contract

```text
Tests\Unit\StageN4PostPurchaseInvoiceContractTest::test_second_invoice_has_an_immutable_tax_and_insurance_contract
Failed asserting that '<?php\n
\n
namespace App\Models;\n
\n
```

## Tests\Unit\StageN4PostPurchaseInvoiceContractTest::test_first_payment_excludes_tax_and_the_seller_is_gated_until_admin_release

```text
Tests\Unit\StageN4PostPurchaseInvoiceContractTest::test_first_payment_excludes_tax_and_the_seller_is_gated_until_admin_release
Failed asserting that '<?php\n
\n
namespace App\Services;\n
\n
```

## Tests\Unit\StageN4PostPurchaseInvoiceContractTest::test_web_api_admin_and_customer_app_are_wired_to_the_same_invoice

```text
Tests\Unit\StageN4PostPurchaseInvoiceContractTest::test_web_api_admin_and_customer_app_are_wired_to_the_same_invoice
Could not read user-app-main/lib/features/order_insurance/screens/customer_order_insurance_screen.dart
Failed asserting that false is not false.
/home/sigma-eg/git/sigma-release-qa.LaQbDf/tests/Unit/StageN4PostPurchaseInvoiceContractTest.php:71
/home/sigma-eg/git/sigma-release-qa.LaQbDf/tests/Unit/StageN4PostPurchaseInvoiceContractTest.php:51
```

## Tests\Unit\StageN5N6ShippingRefundContractTest::test_shipping_settlement_and_private_proof_are_connected_to_all_channels

```text
Tests\Unit\StageN5N6ShippingRefundContractTest::test_shipping_settlement_and_private_proof_are_connected_to_all_channels
Could not resolve application path ../Vendor app/lib/features/order_details/domain/repositories/order_details_repository.dart
Failed asserting that file "/home/new app-20260716T132511Z-1-001/new app/Vendor app/lib/features/order_details/domain/repositories/order_details_repository.dart" exists.
/home/sigma-eg/git/sigma-release-qa.LaQbDf/tests/Unit/StageN5N6ShippingRefundContractTest.php:151
/home/sigma-eg/git/sigma-release-qa.LaQbDf/tests/Unit/StageN5N6ShippingRefundContractTest.php:117
```

## Tests\Unit\StageN5N6ShippingRefundContractTest::test_customer_app_uses_structured_text_address_and_insurance_notice

```text
Tests\Unit\StageN5N6ShippingRefundContractTest::test_customer_app_uses_structured_text_address_and_insurance_notice
Could not resolve application path ../user-app/lib/features/address/domain/models/address_model.dart
Failed asserting that file "/home/user-app-main/user-app-main/lib/features/address/domain/models/address_model.dart" exists.
/home/sigma-eg/git/sigma-release-qa.LaQbDf/tests/Unit/StageN5N6ShippingRefundContractTest.php:151
/home/sigma-eg/git/sigma-release-qa.LaQbDf/tests/Unit/StageN5N6ShippingRefundContractTest.php:117
```

## Tests\Unit\StageN5N6ShippingRefundContractTest::test_authentication_captcha_is_removed_from_platform_and_apps

```text
Tests\Unit\StageN5N6ShippingRefundContractTest::test_authentication_captcha_is_removed_from_platform_and_apps
Could not resolve application path ../user-app/lib
Failed asserting that file "/home/user-app-main/user-app-main/lib" exists.
/home/sigma-eg/git/sigma-release-qa.LaQbDf/tests/Unit/StageN5N6ShippingRefundContractTest.php:151
/home/sigma-eg/git/sigma-release-qa.LaQbDf/tests/Unit/StageN5N6ShippingRefundContractTest.php:125
```

## Tests\Unit\StageN7ReleaseReadinessContractTest::test_mobile_and_web_contracts_hide_order_data_until_allowed_and_expose_required_actions

```text
Tests\Unit\StageN7ReleaseReadinessContractTest::test_mobile_and_web_contracts_hide_order_data_until_allowed_and_expose_required_actions
Could not read vendor-app-main/lib/utill/app_constants.dart
Failed asserting that false is not false.
/home/sigma-eg/git/sigma-release-qa.LaQbDf/tests/Unit/StageN7ReleaseReadinessContractTest.php:165
/home/sigma-eg/git/sigma-release-qa.LaQbDf/tests/Unit/StageN7ReleaseReadinessContractTest.php:79
```

## Tests\Unit\StageN7ReleaseReadinessContractTest::test_release_tooling_and_captcha_shutdown_are_ready_for_manual_acceptance

```text
Tests\Unit\StageN7ReleaseReadinessContractTest::test_release_tooling_and_captcha_shutdown_are_ready_for_manual_acceptance
Could not read scripts/sync-runtime-merge-to-xampp.ps1
Failed asserting that false is not false.
/home/sigma-eg/git/sigma-release-qa.LaQbDf/tests/Unit/StageN7ReleaseReadinessContractTest.php:165
/home/sigma-eg/git/sigma-release-qa.LaQbDf/tests/Unit/StageN7ReleaseReadinessContractTest.php:102
```

## Tests\Unit\StageSevenSupportAndActivationTest::test_activation_and_support_printing_routes_are_registered

```text
Tests\Unit\StageSevenSupportAndActivationTest::test_activation_and_support_printing_routes_are_registered
Failed asserting that false is true.
/home/sigma-eg/git/sigma-release-qa.LaQbDf/tests/Unit/StageSevenSupportAndActivationTest.php:12
```

## Tests\Unit\StageSevenSupportAndActivationTest::test_customer_inbox_is_backed_by_administration_support_tickets

```text
Tests\Unit\StageSevenSupportAndActivationTest::test_customer_inbox_is_backed_by_administration_support_tickets
ErrorException: file_get_contents(E:/project/user-app-main/lib/features/chat/screens/inbox_screen.dart): Failed to open stream: No such file or directory
/home/sigma-eg/git/sigma-release-qa.LaQbDf/vendor/laravel/framework/src/Illuminate/Foundation/Bootstrap/HandleExceptions.php:258
/home/sigma-eg/git/sigma-release-qa.LaQbDf/tests/Unit/StageSevenSupportAndActivationTest.php:19
```

## Tests\Unit\StageSevenSupportAndActivationTest::test_activation_conversation_has_attachments_printing_and_dashboard_shortcut

```text
Tests\Unit\StageSevenSupportAndActivationTest::test_activation_conversation_has_attachments_printing_and_dashboard_shortcut
Failed asserting that '@extends('layouts.admin.app')\n
@section('title', translate('dashboard'))\n
@section('content')\n
<div class="admin-dashboard">\n
```

## Tests\Unit\VendorOrderPhaseTwoTest::test_seller_cannot_cancel_even_with_override_reason

```text
Tests\Unit\VendorOrderPhaseTwoTest::test_seller_cannot_cancel_even_with_override_reason
Failed asserting that exception of type "DomainException" is thrown.
```

## Tests\Unit\VendorOrderPhaseTwoTest::test_legacy_vendor_payment_mutation_is_denied_before_controller

```text
Tests\Unit\VendorOrderPhaseTwoTest::test_legacy_vendor_payment_mutation_is_denied_before_controller
Illuminate\Database\QueryException: SQLSTATE[HY000]: General error: 1 no such table: seller_order_insurances (Connection: sqlite, Database: :memory:, SQL: select * from "seller_order_insurances" where "seller_order_insurances"."order_id" = 1 and "seller_order_insurances"."order_id" is not null limit 1)
/home/sigma-eg/git/sigma-release-qa.LaQbDf/vendor/laravel/framework/src/Illuminate/Database/Connection.php:838
/home/sigma-eg/git/sigma-release-qa.LaQbDf/vendor/laravel/framework/src/Illuminate/Database/Connection.php:794
/home/sigma-eg/git/sigma-release-qa.LaQbDf/vendor/laravel/framework/src/Illuminate/Database/Connection.php:411
```

## Tests\Feature\CriticalBatchConfigurationTest::test_customer_purchase_packages_are_legacy_and_cannot_gate_or_debit_checkout

```text
Tests\Feature\CriticalBatchConfigurationTest::test_customer_purchase_packages_are_legacy_and_cannot_gate_or_debit_checkout
Error: Call to undefined method App\Services\CustomerPurchaseLimitService::isEnabled()
/home/sigma-eg/git/sigma-release-qa.LaQbDf/tests/Feature/CriticalBatchConfigurationTest.php:18
```

## Tests\Feature\CriticalBatchConfigurationTest::test_customer_purchase_package_routes_are_not_registered

```text
Tests\Feature\CriticalBatchConfigurationTest::test_customer_purchase_package_routes_are_not_registered
Failed asserting that true is false.
/home/sigma-eg/git/sigma-release-qa.LaQbDf/tests/Feature/CriticalBatchConfigurationTest.php:29
```

## Tests\Feature\ReleaseApprovalBatchTest::test_legacy_release_data_is_audited_applied_idempotently_and_rolled_back

```text
Tests\Feature\ReleaseApprovalBatchTest::test_legacy_release_data_is_audited_applied_idempotently_and_rolled_back
Failed asserting that 0 is identical to 1.
/home/sigma-eg/git/sigma-release-qa.LaQbDf/tests/Feature/ReleaseApprovalBatchTest.php:40
```

## Tests\Feature\ReleaseApprovalBatchTest::test_non_strict_release_readiness_can_report_local_only_warnings

```text
Tests\Feature\ReleaseApprovalBatchTest::test_non_strict_release_readiness_can_report_local_only_warnings
{
    "database_connection": {
        "status": "pass",
        "message": "Database connection succeeded."
```
