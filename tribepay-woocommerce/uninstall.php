<?php
/**
 * Author: Salman | Version: 1.2.0
 * Description: Preserves financial records and removes scheduled verification.
 * Copyright (C) 2026 Salman. SPDX-License-Identifier: GPL-2.0-or-later
 */
// Retain settings and transaction metadata for reconciliation. Deleting the plugin does not erase financial records.
if (!defined('WP_UNINSTALL_PLUGIN')) { exit; }
wp_clear_scheduled_hook('ztp_verify_payment');
