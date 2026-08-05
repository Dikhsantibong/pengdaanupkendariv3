export type UserRole =
    'administrator' | 'team_leader' | 'pic_perencana' | 'pic_pelaksana';

export type User = {
    id: number;
    name: string;
    email: string;
    role: UserRole;
    role_label: string;
    position: string | null;
    is_active: boolean;
    avatar?: string;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type AuthPermissions = {
    manageMasterData: boolean;
    manageUsers: boolean;
    viewAllProcurements: boolean;
    createProcurement: boolean;
};

export type Auth = {
    user: User;
    permissions: AuthPermissions;
};

/* @chisel-passkeys */
export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};
/* @end-chisel-passkeys */

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};
