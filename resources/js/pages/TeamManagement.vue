<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { UserPlus, Mail, Trash2, Crown, DollarSign, User, Copy, CheckCircle } from 'lucide-vue-next';

interface Team {
    id: string;
    name: string;
    description: string;
}

interface Member {
    id: number;
    name: string;
    email: string;
    roles: string[];
    created_at: string;
}

interface PendingInvitation {
    id: number;
    email: string;
    role: string;
    invited_by: string;
    expires_at: string;
    created_at: string;
    invitation_url: string;
}

interface Props {
    team: Team;
    members: Member[];
    pendingInvitations: PendingInvitation[];
    availableRoles: Record<string, string>;
    canInviteUsers: boolean;
}

const props = defineProps<Props>();
const page = usePage();

console.log('PAGE PROPS:', page.props);
console.log('COMPONENT PROPS:', props);

const { t } = useI18n();

const showInviteDialog = ref(false);
const showRemoveDialog = ref(false);
const memberToRemove = ref<Member | null>(null);
const copiedUrls = ref<Set<string>>(new Set());
const showStripeModal = ref(false);
const stripeForm = ref({
    public_key: '',
    secret_key: '',
    webhook_secret: '',
});

// Get current user email
const currentUserEmail = computed(() => {
    return page.props.auth?.user?.email || '';
});

// Form for sending invitations
const inviteForm = useForm({
    email: '',
    role: '',
});

// Form for updating user roles
const updateRoleForm = useForm({
    role: '',
});

const isAdmin = computed(() => {
    const roles = (page.props.auth?.user as any)?.roles || [];
    return roles.some((role: any) => role.name === 'Admin');
});

const getRoleIcon = (role: string) => {
    switch (role) {
        case 'Admin':
            return Crown;
        case 'Finance':
            return DollarSign;
        case 'Employee':
            return User;
        default:
            return User;
    }
};

const getRoleColor = (role: string) => {
    switch (role) {
        case 'Admin':
            return 'text-red-600 bg-red-100';
        case 'Finance':
            return 'text-green-600 bg-green-100';
        case 'Employee':
            return 'text-blue-600 bg-blue-100';
        default:
            return 'text-gray-600 bg-gray-100';
    }
};

const sendInvitation = () => {
    inviteForm.post(route('team.invitations.send'), {
        onSuccess: () => {
            inviteForm.reset();
            showInviteDialog.value = false;
        },
    });
};

const updateUserRole = (user: Member, newRole: string) => {
    updateRoleForm.role = newRole;
    updateRoleForm.patch(route('team.users.role', user.id), {
        preserveScroll: true,
    });
};

const cancelInvitation = (invitationId: number) => {
    useForm({}).delete(route('team.invitations.cancel', invitationId), {
        preserveScroll: true,
    });
};

const confirmRemoveUser = (member: Member) => {
    memberToRemove.value = member;
    showRemoveDialog.value = true;
};

const removeUser = () => {
    if (memberToRemove.value) {
        useForm({}).delete(route('team.users.remove', memberToRemove.value.id), {
            onSuccess: () => {
                showRemoveDialog.value = false;
                memberToRemove.value = null;
            },
        });
    }
};

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

const copyToClipboard = async (url: string, identifier: string) => {
    try {
        await navigator.clipboard.writeText(url);
        copiedUrls.value.add(identifier);

        // Reset the copied state after 2 seconds
        setTimeout(() => {
            copiedUrls.value.delete(identifier);
        }, 2000);
    } catch (err) {
        console.error('Failed to copy: ', err);
        // Fallback for older browsers
        const textArea = document.createElement('textarea');
        textArea.value = url;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);

        copiedUrls.value.add(identifier);
        setTimeout(() => {
            copiedUrls.value.delete(identifier);
        }, 2000);
    }
};

const submitStripeForm = () => {
    // Replace with actual API call to save Stripe settings
    alert('Stripe settings saved!\n' + JSON.stringify(stripeForm.value, null, 2));
    showStripeModal.value = false;
};
</script>

<template>
    <Head :title="t('team_management')" />

    <AppLayout>
        <div class="p-6 space-y-6">
            <!-- Success Message with Copy Link -->
            <div
                v-if="$page.props.flash?.success && $page.props.flash?.invitationUrl"
                class="bg-green-50 border border-green-200 rounded-lg p-4"
            >
                <div class="flex items-start gap-3">
                    <CheckCircle class="w-5 h-5 text-green-600 mt-0.5 flex-shrink-0" />
                    <div class="flex-1">
                        <h3 class="text-green-800 font-medium">{{ $page.props.flash.success }}</h3>
                        <p class="text-green-700 text-sm mt-1">
                            {{ t('send_invitation') }} <strong>{{ $page.props.flash.invitationEmail }}</strong>
                        </p>
                        <div class="mt-3 flex items-center gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                @click="copyToClipboard($page.props.flash.invitationUrl, 'flash-invitation')"
                                class="text-blue-600 hover:text-blue-700 hover:bg-blue-50"
                            >
                                <CheckCircle
                                    v-if="copiedUrls.has('flash-invitation')"
                                    class="w-4 h-4 text-green-600"
                                />
                                <Copy v-else class="w-4 h-4" />
                                {{ copiedUrls.has('flash-invitation') ? t('success') : t('send_invitation') }}
                            </Button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold">{{ t('team_management') }}</h1>
                    <p class="text-gray-600 mt-1">
                        {{ t('manage_team') }} {{ team.name }}
                    </p>
                </div>
                <Button v-if="canInviteUsers" @click="showInviteDialog = true" class="flex items-center gap-2">
                    <UserPlus class="w-4 h-4" />
                    {{ t('invite_member') }}
                </Button>
            </div>

            <!-- Team Members -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <User class="w-5 h-5" />
                        {{ t('team_members') }} ({{ members.length }})
                    </CardTitle>
                    <CardDescription>
                        {{ t('manage_team') }}
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="space-y-4">
                        <div
                            v-for="member in members"
                            :key="member.id"
                            class="flex items-center justify-between p-4 border rounded-lg hover:bg-gray-50"
                        >
                            <div class="flex-1">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 bg-gray-200 rounded-full flex items-center justify-center">
                                        <User class="w-5 h-5 text-gray-600" />
                                    </div>
                                    <div>
                                        <h3 class="font-medium">{{ member.name }}</h3>
                                        <p class="text-sm text-gray-600">{{ member.email }}</p>
                                        <p class="text-xs text-gray-500">
                                            Joined {{ formatDate(member.created_at) }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <!-- Role Badge -->
                                <div
                                    v-for="role in member.roles"
                                    :key="role"
                                    class="flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium"
                                    :class="getRoleColor(role)"
                                >
                                    <component :is="getRoleIcon(role)" class="w-3 h-3" />
                                    {{ role }}
                                </div>

                                <!-- Role Selector -->
                                <div class="relative">
                                    <select
                                        :value="member.roles[0] || ''"
                                        @change="updateUserRole(member, ($event.target as HTMLSelectElement).value)"
                                        :disabled="member.email === currentUserEmail"
                                        class="flex h-9 w-32 rounded-md border border-input bg-background px-3 py-1 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                    >
                                        <option value="" disabled>Select role</option>
                                        <option
                                            v-for="(label, value) in availableRoles"
                                            :key="value"
                                            :value="value"
                                        >
                                            {{ label }}
                                        </option>
                                    </select>
                                </div>

                                <!-- Remove User Button - Hide for current user -->
                                <Button
                                    v-if="member.email !== currentUserEmail"
                                    variant="outline"
                                    size="sm"
                                    @click="confirmRemoveUser(member)"
                                    class="text-red-600 hover:text-red-700 hover:bg-red-50"
                                >
                                    <Trash2 class="w-4 h-4" />
                                </Button>
                                <div v-else class="w-9 h-9"></div><!-- Placeholder to maintain layout -->
                            </div>
                        </div>

                        <div v-if="members.length === 0" class="text-center py-8 text-gray-500">
                            No team members found.
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Pending Invitations -->
            <Card v-if="pendingInvitations.length > 0">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Mail class="w-5 h-5" />
                        Pending Invitations ({{ pendingInvitations.length }})
                    </CardTitle>
                    <CardDescription>
                        Invitations that have been sent but not yet accepted
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="space-y-4">
                        <div
                            v-for="invitation in pendingInvitations"
                            :key="invitation.id"
                            class="flex items-center justify-between p-4 border rounded-lg bg-yellow-50 border-yellow-200"
                        >
                            <div class="flex-1">
                                <div class="flex items-center gap-3">
                                    <Mail class="w-5 h-5 text-yellow-600" />
                                    <div>
                                        <h3 class="font-medium">{{ invitation.email }}</h3>
                                        <p class="text-sm text-gray-600">
                                            Invited by {{ invitation.invited_by }} •
                                            Role: {{ invitation.role }}
                                        </p>
                                        <p class="text-xs text-gray-500">
                                            Expires {{ formatDate(invitation.expires_at) }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <!-- Copy Invitation URL Button -->
                                <Button
                                    variant="outline"
                                    size="sm"
                                    @click="copyToClipboard(invitation.invitation_url, `invitation-${invitation.id}`)"
                                    class="text-blue-600 hover:text-blue-700 hover:bg-blue-50"
                                >
                                    <CheckCircle
                                        v-if="copiedUrls.has(`invitation-${invitation.id}`)"
                                        class="w-4 h-4 text-green-600"
                                    />
                                    <Copy v-else class="w-4 h-4" />
                                    {{ copiedUrls.has(`invitation-${invitation.id}`) ? t('copied') : t('copy_link') }}
                                </Button>

                                <!-- Cancel Invitation Button -->
                                <Button
                                    variant="outline"
                                    size="sm"
                                    @click="cancelInvitation(invitation.id)"
                                    class="text-red-600 hover:text-red-700 hover:bg-red-50"
                                >
                                    <Trash2 class="w-4 h-4" />
                                    Cancel
                                </Button>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Invite Member Dialog -->
            <Dialog v-model:open="showInviteDialog">
                <DialogContent class="sm:max-w-md">
                    <DialogHeader class="space-y-3">
                        <DialogTitle>Invite Team Member</DialogTitle>
                        <DialogDescription>
                            Send an invitation to join {{ team.name }}. They will receive an email with instructions to create their account.
                        </DialogDescription>
                    </DialogHeader>
                    <div class="mt-6">
                        <form @submit.prevent="sendInvitation" class="space-y-6">
                            <div>
                                <Label for="email">Email Address</Label>
                                <Input
                                    id="email"
                                    v-model="inviteForm.email"
                                    type="email"
                                    placeholder="Enter email address"
                                    required
                                    :disabled="inviteForm.processing"
                                    class="mt-2"
                                />
                                <div v-if="inviteForm.errors.email" class="text-sm text-red-600 mt-1">
                                    {{ inviteForm.errors.email }}
                                </div>
                            </div>
                            <div>
                                <Label for="role">Role</Label>
                                <div class="relative mt-2">
                                    <select
                                        v-model="inviteForm.role"
                                        required
                                        class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                    >
                                        <option value="" disabled>Select a role</option>
                                        <option
                                            v-for="(label, value) in availableRoles"
                                            :key="value"
                                            :value="value"
                                        >
                                            {{ label }}
                                        </option>
                                    </select>
                                </div>
                                <div v-if="inviteForm.errors.role" class="text-sm text-red-600 mt-1">
                                    {{ inviteForm.errors.role }}
                                </div>
                            </div>
                            <DialogFooter class="pt-4">
                                <Button type="button" variant="outline" @click="showInviteDialog = false">
                                    Cancel
                                </Button>
                                <Button type="submit" :disabled="inviteForm.processing">
                                    {{ inviteForm.processing ? 'Sending...' : 'Send Invitation' }}
                                </Button>
                            </DialogFooter>
                        </form>
                    </div>
                </DialogContent>
            </Dialog>

            <!-- Remove User Confirmation Dialog -->
            <Dialog v-model:open="showRemoveDialog">
                <DialogContent class="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Remove Team Member</DialogTitle>
                        <DialogDescription>
                            Are you sure you want to remove {{ memberToRemove?.name }} from the team?
                            This action cannot be undone.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button type="button" variant="outline" @click="showRemoveDialog = false">
                            Cancel
                        </Button>
                        <Button type="button" variant="destructive" @click="removeUser">
                            Remove Member
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <!-- Stripe Payment Gateway Setup (Admin only) -->
            <Card v-if="isAdmin">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <DollarSign class="w-5 h-5 text-purple-600" />
                        Stripe Payment Gateway Setup
                    </CardTitle>
                    <CardDescription>
                        Connect and configure your Stripe account to enable online payments for your team.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="space-y-4">
                        <p class="text-gray-700">To accept payments online, you need to connect your Stripe account. Only admins can see and manage this section.</p>
                        <Button variant="outline" class="text-purple-700 border-purple-300 hover:bg-purple-50" @click="showStripeModal = true">
                            Connect Stripe Account
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <!-- Stripe Payment Gateway Setup Modal -->
            <Dialog v-model:open="showStripeModal">
                <DialogContent class="max-w-md w-full p-0">
                    <Card class="shadow-none border-0">
                        <CardHeader class="pb-0">
                            <DialogHeader>
                                <DialogTitle class="flex items-center gap-2">
                                    <DollarSign class="w-5 h-5 text-purple-600" />
                                    Stripe Payment Gateway Setup
                                </DialogTitle>
                                <DialogDescription>
                                    Enter your Stripe API keys to enable online payments. You can find these in your Stripe dashboard.
                                </DialogDescription>
                            </DialogHeader>
                        </CardHeader>
                        <CardContent class="pt-0">
                            <form @submit.prevent="submitStripeForm" class="space-y-5 mt-2">
                                <div class="space-y-2">
                                    <Label for="stripe-public-key">Stripe Public Key</Label>
                                    <div class="relative">
                                        <Input id="stripe-public-key" v-model="stripeForm.public_key" type="text" required placeholder="pk_live_..." class="pr-10" />
                                        <DollarSign class="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" />
                                    </div>
                                </div>
                                <div class="space-y-2">
                                    <Label for="stripe-secret-key">Stripe Secret Key</Label>
                                    <div class="relative">
                                        <Input id="stripe-secret-key" v-model="stripeForm.secret_key" type="text" required placeholder="sk_live_..." class="pr-10" />
                                        <DollarSign class="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" />
                                    </div>
                                </div>
                                <div class="space-y-2">
                                    <Label for="stripe-webhook-secret">Webhook Secret</Label>
                                    <div class="relative">
                                        <Input id="stripe-webhook-secret" v-model="stripeForm.webhook_secret" type="text" required placeholder="whsec_..." class="pr-10" />
                                        <Mail class="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" />
                                    </div>
                                </div>
                                <DialogFooter class="flex justify-end gap-2 pt-4">
                                    <Button type="button" variant="outline" @click="showStripeModal = false">Cancel</Button>
                                    <Button type="submit">Save</Button>
                                </DialogFooter>
                            </form>
                        </CardContent>
                    </Card>
                </DialogContent>
            </Dialog>
        </div>
    </AppLayout>
</template>
