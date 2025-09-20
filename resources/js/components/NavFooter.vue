<script setup lang="ts">
import { SidebarGroup, SidebarGroupContent, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { useDirection } from '@/composables/useDirection';
import { useI18n } from 'vue-i18n';
import { usePage, useForm } from '@inertiajs/vue3';
import { Globe } from 'lucide-vue-next';
import { computed } from 'vue';
import { setDirection } from '@/lib/i18n';

interface Props {
    items: NavItem[];
    class?: string;
}

defineProps<Props>();

const { isRtl } = useDirection();
const { locale } = useI18n();
const page = usePage();

// Get current user language
const currentLanguage = computed(() => {
    return (page.props.auth.user as any)?.language || 'en';
});

// Toggle language text
const toggleLanguageText = computed(() => {
    return currentLanguage.value === 'ar' ? 'English' : 'العربية';
});

// Form to update language
const form = useForm({
    name: (page.props.auth.user as any)?.name || '',
    email: (page.props.auth.user as any)?.email || '',
    language: currentLanguage.value === 'ar' ? 'en' : 'ar',
});

// Toggle language function
const toggleLanguage = () => {
    const newLanguage = currentLanguage.value === 'ar' ? 'en' : 'ar';
    
    // Update form language
    form.language = newLanguage;
    
    // Update frontend locale and direction
    locale.value = newLanguage as any;
    setDirection(newLanguage);
    
    // Submit form to update backend
    form.patch(route('profile.update'), {
        preserveScroll: true,
        onSuccess: () => {
            // Language change successful
        },
    });
};
</script>

<template>
    <SidebarGroup :class="`group-data-[collapsible=icon]:p-0 ${$props.class || ''}`">
        <SidebarGroupContent>
            <SidebarMenu>
                <!-- Language Toggle Button -->
                <SidebarMenuItem>
                    <SidebarMenuButton 
                        @click="toggleLanguage"
                        :disabled="form.processing"
                        class="text-neutral-600 hover:text-neutral-800 dark:text-neutral-300 dark:hover:text-neutral-100" 
                        :class="{ 'flex-row-reverse': isRtl }"
                    >
                        <template v-if="isRtl">
                            <span class="group-data-[collapsible=icon]:hidden">{{ toggleLanguageText }}</span>
                            <Globe class="size-4 group-data-[collapsible=icon]:mx-auto" />
                        </template>
                        <template v-else>
                            <Globe class="size-4 group-data-[collapsible=icon]:mx-auto" />
                            <span class="group-data-[collapsible=icon]:hidden">{{ toggleLanguageText }}</span>
                        </template>
                    </SidebarMenuButton>
                </SidebarMenuItem>
                
                <!-- Existing Nav Items -->
                <SidebarMenuItem v-for="item in items" :key="item.title">
                    <SidebarMenuButton class="text-neutral-600 hover:text-neutral-800 dark:text-neutral-300 dark:hover:text-neutral-100" as-child>
                        <a :href="item.href" target="_blank" rel="noopener noreferrer" :class="{ 'flex-row-reverse': isRtl }">
                            <template v-if="isRtl">
                                <span class="group-data-[collapsible=icon]:hidden">{{ item.title }}</span>
                                <component :is="item.icon" class="group-data-[collapsible=icon]:mx-auto" />
                            </template>
                            <template v-else>
                                <component :is="item.icon" class="group-data-[collapsible=icon]:mx-auto" />
                                <span class="group-data-[collapsible=icon]:hidden">{{ item.title }}</span>
                            </template>
                        </a>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarGroupContent>
    </SidebarGroup>
</template>
