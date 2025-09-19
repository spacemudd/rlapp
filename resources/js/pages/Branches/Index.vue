<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Building2, Plus, Search, MoreHorizontal, Edit, Eye, Trash2 } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

interface Branch {
    id: string;
    name: string;
    address?: string;
    city?: string;
    country: string;
    description?: string;
    status: 'active' | 'inactive';
    created_at: string;
    updated_at: string;
    full_address?: string;
}

interface PaginatedBranches {
    data: Branch[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
}

interface Props {
    branches: PaginatedBranches;
    filters: {
        search?: string;
        status?: string;
    };
    statuses: string[];
}

const props = defineProps<Props>();

const { t } = useI18n();

const search = ref(props.filters.search || '');
const selectedStatus = ref(props.filters.status || '');

const updateFilters = () => {
    const params = new URLSearchParams();
    if (search.value) params.set('search', search.value);
    if (selectedStatus.value) params.set('status', selectedStatus.value);
    const queryString = params.toString();
    const url = queryString ? `${window.location.pathname}?${queryString}` : window.location.pathname;
    router.get(url, {}, { preserveState: true, replace: true });
};

let searchTimeout: ReturnType<typeof setTimeout>;
watch(search, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(updateFilters, 300);
});
watch(selectedStatus, updateFilters);

const getStatusBadge = (status: string) => {
    switch (status) {
        case 'active':
            return 'bg-green-100 text-green-800 hover:bg-green-200';
        case 'inactive':
            return 'bg-red-100 text-red-800 hover:bg-red-200';
        default:
            return 'bg-gray-100 text-gray-800 hover:bg-gray-200';
    }
};

const clearFilters = () => {
    search.value = '';
    selectedStatus.value = '';
    router.get('/branches');
};

const hasActiveFilters = computed(() => search.value || selectedStatus.value);

const deleteBranch = (branch: Branch) => {
    if (confirm(t('are_you_sure_delete_branch', { name: branch.name }))) {
        router.delete(`/branches/${branch.id}`);
    }
};
</script>

<template>
    <Head :title="t('branches')" />
    <AppLayout>
        <div class="p-6">
            <div class="mb-6">
                <div class="sm:flex sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:text-3xl">
                            {{ t('branches') }}
                        </h2>
                        <p class="mt-1 text-sm text-gray-500">
                            {{ t('manage_branches') }}
                        </p>
                    </div>
                    <div class="mt-4 sm:mt-0">
                        <Link href="/branches/create">
                            <Button>
                                <Plus class="w-4 h-4 mr-2" />
                                {{ t('create_branch') }}
                            </Button>
                        </Link>
                    </div>
                </div>
            </div>

            <Card class="mb-6">
                <CardContent class="pt-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="relative">
                            <Search class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-4 h-4" />
                            <Input v-model="search" :placeholder="t('search_branches')" class="pl-10" />
                        </div>
                        <div>
                            <select v-model="selectedStatus" class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2">
                                <option value="">{{ t('all_statuses') }}</option>
                                <option v-for="status in statuses" :key="status" :value="status">{{ status }}</option>
                            </select>
                        </div>
                        <div class="flex items-center space-x-2">
                            <Button v-if="hasActiveFilters" variant="outline" @click="clearFilters" class="flex-1">{{ t('clear_search') }}</Button>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card class="mb-6">
                <CardContent class="p-0">
                    <div v-if="branches.data.length > 0" class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="border-b border-gray-200 bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('branch') }}</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('address') }}</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('status') }}</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('actions') }}</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="branch in branches.data" :key="branch.id" class="hover:bg-gray-50 transition-colors">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <Building2 class="w-5 h-5 text-blue-600 mr-3" />
                                            <div>
                                                <div class="text-sm font-medium text-gray-900">{{ branch.name }}</div>
                                                <div v-if="branch.description" class="text-sm text-gray-500 max-w-xs truncate">{{ branch.description }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">{{ branch.full_address || t('no_address_provided') }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <Badge :class="getStatusBadge(branch.status)">{{ branch.status }}</Badge>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <DropdownMenu>
                                            <DropdownMenuTrigger as-child>
                                                <Button variant="ghost" size="sm" class="w-8 h-8 p-0">
                                                    <MoreHorizontal class="w-4 h-4" />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end">
                                                <DropdownMenuItem as-child>
                                                    <Link :href="`/branches/${branch.id}`" class="flex items-center cursor-pointer">
                                                        <Eye class="w-4 h-4 mr-2" />
                                                        {{ t('view') }}
                                                    </Link>
                                                </DropdownMenuItem>
                                                <DropdownMenuItem as-child>
                                                    <Link :href="`/branches/${branch.id}/edit`" class="flex items-center cursor-pointer">
                                                        <Edit class="w-4 h-4 mr-2" />
                                                        {{ t('edit') }}
                                                    </Link>
                                                </DropdownMenuItem>
                                                <DropdownMenuSeparator />
                                                <DropdownMenuItem @click="deleteBranch(branch)" class="text-red-600 focus:text-red-600 cursor-pointer">
                                                    <Trash2 class="w-4 h-4 mr-2" />
                                                    {{ t('delete') }}
                                                </DropdownMenuItem>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-else class="text-center py-12">
                        <Building2 class="mx-auto h-12 w-12 text-gray-400" />
                        <h3 class="mt-2 text-lg font-medium text-gray-900">{{ t('no_branches_found') }}</h3>
                        <p class="mt-1 text-gray-500">{{ hasActiveFilters ? t('try_adjusting_search') : t('get_started_first_branch') }}</p>
                        <div class="mt-6">
                            <Link v-if="!hasActiveFilters" href="/branches/create">
                                <Button>
                                    <Plus class="w-4 h-4 mr-2" />
                                    {{ t('create_branch') }}
                                </Button>
                            </Link>
                            <Button v-else variant="outline" @click="clearFilters">{{ t('clear_search') }}</Button>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <div v-if="branches.last_page > 1" class="flex items-center justify-between">
                <p class="text-sm text-gray-700">{{ t('showing') }} {{ branches.from }} {{ t('to') || 'to' }} {{ branches.to }} {{ t('of') || 'of' }} {{ branches.total }} {{ t('results') }}</p>
                <div class="flex space-x-2">
                    <Link v-if="branches.current_page > 1" :href="`/branches?page=${branches.current_page - 1}`" preserve-state>
                        <Button variant="outline" size="sm">{{ t('previous') }}</Button>
                    </Link>
                    <Link v-if="branches.current_page < branches.last_page" :href="`/branches?page=${branches.current_page + 1}`" preserve-state>
                        <Button variant="outline" size="sm">{{ t('next') }}</Button>
                    </Link>
                </div>
            </div>
        </div>
    </AppLayout>
    </template>


