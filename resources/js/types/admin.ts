export type AdminUser = {
    id: number;
    name: string;
    email: string;
    is_admin: boolean;
    is_active: boolean;
    must_change_password: boolean;
};

export type AdminUserRow = AdminUser & {
    pages_printed: number;
};
