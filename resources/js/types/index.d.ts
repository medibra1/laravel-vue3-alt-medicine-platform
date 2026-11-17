import { Config } from 'ziggy-js';

export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string;
}

export type AppModule = 'patients' | 'treatments' | 'scheduling' | 'practitioners' | 'billing';

export interface AuthProps {
    user: User;
    is_super_admin: boolean;
    is_admin: boolean;
    is_global_admin: boolean;
    is_manager: boolean;
    is_practitioner: boolean;
    modules: AppModule[];
    unread_notifications_count: number;
    accessible_centers: { id: number; name: string }[];
    active_center_id: number | null;
    active_center: { id: number; name: string; currency: string; timezone: string } | null;
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    locale: string;
    auth: AuthProps;
    ziggy: Config & { location: string };
};
