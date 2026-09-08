<?php
/**
 * 角色權限的唯一判斷入口。
 * 權限值不可用大小比較：新增角色時，必須在對應的允許清單中明確加入。
 */
const USER_LIMIT_DISABLED = 0;
const USER_LIMIT_ADMIN = 1;
const USER_LIMIT_EMPLOYEE = 2;
const USER_LIMIT_EXTERNAL = 3;

function current_limit(): ?int
{
    $limit = $_SESSION['admlimit'] ?? null;

    return is_int($limit) ? $limit : null;
}

function is_logged_in(): bool
{
    return current_limit() !== null;
}

function is_admin(): bool
{
    return current_limit() === USER_LIMIT_ADMIN;
}

function can_manage_users(): bool
{
    return is_admin();
}

function can_access_self(): bool
{
    return in_array(current_limit(), [USER_LIMIT_ADMIN, USER_LIMIT_EMPLOYEE, USER_LIMIT_EXTERNAL], true);
}

function can_view_business_data(): bool
{
    return in_array(current_limit(), [USER_LIMIT_ADMIN, USER_LIMIT_EMPLOYEE], true);
}
