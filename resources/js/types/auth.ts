export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    is_admin: boolean;
    is_active: boolean;
    must_change_password: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
};
