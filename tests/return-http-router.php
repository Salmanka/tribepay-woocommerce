<?php
/**
 * Author: Salman | Version: 1.2.0
 * Description: Local HTTP test adapter with mocked WooCommerce and provider dependencies.
 * Copyright (C) 2026 Salman. SPDX-License-Identifier: GPL-2.0-or-later
 */
// Local HTTP harness: production handler, mocked WooCommerce and provider status.
$incoming_http_request = $_GET;
ob_start(); require __DIR__ . '/run.php'; ob_end_clean();
$_GET = $incoming_http_request;
$GLOBALS['order'] = new FakeOrder;
$case = $incoming_http_request['case'] ?? 'paid';
if ($case === 'paid') { $GLOBALS['order']->status = 'processing'; }
else {
 $GLOBALS['order']->meta = array('_ztp_attempts' => array($ref => $attempt), '_ztp_current' => $ref);
 $GLOBALS['responses'] = array(response(array_merge($data, array('status' => $case === 'verified' ? 'completed' : 'pending'))));
}
try { ZTP_Service::handle_return(); }
catch (RedirectCaptured $e) { header('Location: '.$e->getMessage(), true, 302); }
catch (ReturnRejected $e) { http_response_code(403); echo $e->getMessage(); }
