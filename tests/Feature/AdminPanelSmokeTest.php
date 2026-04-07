<?php

namespace Tests\Feature;

use Tests\TestCase;

class AdminPanelSmokeTest extends TestCase
{
    public function test_admin_login_page_is_reachable(): void
    {
        $this->get('/admin/login')->assertOk();
    }
}
