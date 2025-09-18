<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { BookOpen, Folder, LayoutGrid, Users, Receipt, Settings, Car, FileText, MapPin, Calculator, Calendar } from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useDirection } from '@/composables/useDirection';

const page = usePage();
const { t } = useI18n();
const { sidebarSide } = useDirection();

// Get user permissions from page props
const userPermissions = computed(() => {
    return page.props.auth?.permissions || [];
});

// Check if user can manage team settings
const canManageTeam = computed(() => {
    return userPermissions.value.includes('manage team settings');
});

// Check if user can view financial reports
const canViewFinancialReports = computed(() => {
    return userPermissions.value.includes('view financial reports') ||
           userPermissions.value.includes('manage team settings'); // Team managers can access accounting
});

const mainNavItems = computed<NavItem[]>(() => {
    const items: NavItem[] = [
        {
            title: t('dashboard'),
            href: '/dashboard',
            icon: LayoutGrid,
        },
        {
            title: t('customers'),
            href: '/customers',
            icon: Users,
        },
        {
            title: t('contracts'),
            href: '/contracts',
            icon: FileText,
        },
        {
            title: t('invoices'),
            href: '/invoices',
            icon: Receipt,
        },
        {
            title: t('vehicles'),
            href: '/vehicles',
            icon: Car,
        },
        {
            title: t('reservations'),
            href: '/reservations',
            icon: Calendar,
        },
        {
            title: t('locations'),
            href: '/locations',
            icon: MapPin,
        },
    ];

    // Add Accounting link for users with financial permissions
    if (canViewFinancialReports.value) {
        items.push({
            title: t('accounting'),
            href: '/accounting',
            icon: Calculator,
        });
    }

    items.push({
        title: t('traffic_violations'),
        href: '/traffic-violations',
        icon: FileText,
    });

    // Add Settings link
    items.push({
        title: t('system_settings'),
        href: '/system-settings',
        icon: Settings,
    });

    // Add Team link only for users with manage team settings permission
    if (canManageTeam.value) {
        items.push({
            title: t('team_management'),
            href: '/team',
            icon: Settings,
        });
    }

    return items;
});

const footerNavItems: NavItem[] = [
    // {
    //     title: 'Github Repo',
    //     href: 'https://github.com/laravel/vue-starter-kit',
    //     icon: Folder,
    // },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset" :side="sidebarSide">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="route('dashboard')">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
