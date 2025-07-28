import type { LucideIcon } from 'lucide-vue-next';
import type { Config } from 'ziggy-js';

export interface Auth {
    user: User;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavItem {
    title: string;
    href: string;
    icon?: LucideIcon;
    isActive?: boolean;
}

export type AppPageProps<T extends Record<string, unknown> = Record<string, unknown>> = T & {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    ziggy: Config & { location: string };
    sidebarOpen: boolean;
};

export interface Message {
    id: number;
    chat_id: number;
    sender_id: number;
    sender_type: 'operator' | 'client';
    status: 'sent' | 'delivered' | 'read';
    is_edited: boolean;
    content: string;
    edited_at: string | null;
    created_at: string;
    updated_at: string;
}

export interface Chat {
    id: number;
    client_id: number;
    client: Client | null;
    operator_id: number | null;
    operator: Operator | null;
    messages: Message[];
    status: 'pending' | 'active' | 'closed';
    created_at: string;
    updated_at: string;
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
}

export type BreadcrumbItemType = BreadcrumbItem;
