<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { ChevronDown, Mail, ShieldCheck, UserPlus, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import CancelInvitationModal from '@/components/CancelInvitationModal.vue';
import DeleteTeamModal from '@/components/DeleteTeamModal.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import InviteMemberModal from '@/components/InviteMemberModal.vue';
import RemoveMemberModal from '@/components/RemoveMemberModal.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useInitials } from '@/composables/useInitials';
import { edit, index, update } from '@/routes/teams';
import { update as updateMember } from '@/routes/teams/members';
import { update as updateMemberAccess } from '@/routes/teams/members/access';
import type {
    DataScopeOption,
    Department,
    RoleOption,
    Team,
    TeamInvitation,
    TeamMember,
    TeamPermissions,
} from '@/types';

type Props = {
    team: Team;
    members: TeamMember[];
    invitations: TeamInvitation[];
    permissions: TeamPermissions;
    availableRoles: RoleOption[];
    canManagePermissions: boolean;
    departments: Pick<Department, 'id' | 'name'>[];
    dataScopes: DataScopeOption[];
};

const props = defineProps<Props>();

const currencyOptions = [
    { code: 'NGN', label: 'Nigerian Naira' },
    { code: 'USD', label: 'US Dollar' },
    { code: 'EUR', label: 'Euro' },
    { code: 'GBP', label: 'British Pound' },
    { code: 'GHS', label: 'Ghanaian Cedi' },
    { code: 'KES', label: 'Kenyan Shilling' },
    { code: 'ZAR', label: 'South African Rand' },
    { code: 'XOF', label: 'West African CFA Franc' },
    { code: 'CAD', label: 'Canadian Dollar' },
    { code: 'AUD', label: 'Australian Dollar' },
];

defineOptions({
    layout: (props: { team: Team }) => ({
        breadcrumbs: [
            {
                title: 'Teams',
                href: index(),
            },
            {
                title: props.team.name,
                href: edit(props.team.slug),
            },
        ],
    }),
});

const { getInitials } = useInitials();

const inviteDialogOpen = ref(false);
const deleteDialogOpen = ref(false);
const removeMemberDialogOpen = ref(false);
const memberToRemove = ref<TeamMember | null>(null);
const cancelInvitationDialogOpen = ref(false);
const invitationToCancel = ref<TeamInvitation | null>(null);

const pageTitle = computed(() =>
    props.permissions.canUpdateTeam
        ? `Edit ${props.team.name}`
        : `View ${props.team.name}`,
);

const updateMemberRole = (member: TeamMember, newRole: string) => {
    router.visit(updateMember([props.team.slug, member.id]), {
        data: { role: newRole },
        preserveScroll: true,
    });
};

const editingAccessFor = ref<number | null>(null);

const toggleAccessEditor = (member: TeamMember) => {
    editingAccessFor.value =
        editingAccessFor.value === member.id ? null : member.id;
};

const confirmRemoveMember = (member: TeamMember) => {
    memberToRemove.value = member;
    removeMemberDialogOpen.value = true;
};

const confirmCancelInvitation = (invitation: TeamInvitation) => {
    invitationToCancel.value = invitation;
    cancelInvitationDialogOpen.value = true;
};
</script>

<template>
    <Head :title="pageTitle" />

    <h1 class="sr-only">{{ pageTitle }}</h1>

    <div class="flex flex-col space-y-10">
        <!-- Team Name Section -->
        <div v-if="permissions.canUpdateTeam" class="space-y-6">
            <Heading
                variant="small"
                title="Team settings"
                description="Update your team name and settings"
            />

            <Form
                v-bind="update.form(team.slug)"
                class="space-y-6"
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-2">
                    <Label for="name">Team name</Label>
                    <Input
                        id="name"
                        name="name"
                        data-test="team-name-input"
                        :default-value="team.name"
                        required
                    />
                    <InputError :message="errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="currency">Currency</Label>
                    <select
                        id="currency"
                        name="currency"
                        :value="team.currency ?? 'NGN'"
                        class="h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                    >
                        <option
                            v-for="option in currencyOptions"
                            :key="option.code"
                            :value="option.code"
                        >
                            {{ option.code }} — {{ option.label }}
                        </option>
                    </select>
                    <p class="text-xs text-muted-foreground">
                        Used to display all prices, invoices, and receipts.
                    </p>
                    <InputError :message="errors.currency" />
                </div>

                <div class="flex items-center gap-4">
                    <Button
                        type="submit"
                        data-test="team-save-button"
                        :disabled="processing"
                    >
                        Save
                    </Button>
                </div>
            </Form>
        </div>

        <div v-else class="space-y-6">
            <Heading variant="small" :title="team.name" />
        </div>

        <!-- Members Section -->
        <div class="space-y-6">
            <div class="flex items-center justify-between">
                <Heading
                    variant="small"
                    title="Team members"
                    :description="
                        permissions.canCreateInvitation
                            ? 'Manage who belongs to this team'
                            : ''
                    "
                />

                <Button
                    v-if="permissions.canCreateInvitation"
                    data-test="invite-member-button"
                    @click="inviteDialogOpen = true"
                >
                    <UserPlus /> Invite member
                </Button>
            </div>

            <div class="space-y-3">
                <div
                    v-for="member in members"
                    :key="member.id"
                    data-test="member-row"
                    class="rounded-lg border p-4"
                >
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-4">
                            <Avatar class="h-10 w-10">
                                <AvatarImage
                                    v-if="member.avatar"
                                    :src="member.avatar"
                                    :alt="member.name"
                                />
                                <AvatarFallback>{{
                                    getInitials(member.name)
                                }}</AvatarFallback>
                            </Avatar>
                            <div>
                                <div class="font-medium">
                                    {{ member.name }}
                                </div>
                                <div class="text-sm text-muted-foreground">
                                    {{ member.email }}
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <DropdownMenu
                                v-if="
                                    member.role !== 'owner' &&
                                    permissions.canUpdateMember
                                "
                            >
                                <DropdownMenuTrigger as-child>
                                    <Button
                                        data-test="member-role-trigger"
                                        variant="outline"
                                        size="sm"
                                    >
                                        {{ member.role_label }}
                                        <ChevronDown
                                            class="ml-2 h-4 w-4 opacity-50"
                                        />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent>
                                    <DropdownMenuItem
                                        v-for="role in availableRoles"
                                        :key="role.value"
                                        data-test="member-role-option"
                                        @click="
                                            updateMemberRole(member, role.value)
                                        "
                                    >
                                        {{ role.label }}
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                            <Badge v-else variant="secondary">
                                {{ member.role_label }}
                            </Badge>

                            <TooltipProvider
                                v-if="
                                    member.role !== 'owner' &&
                                    permissions.canRemoveMember
                                "
                            >
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <Button
                                            data-test="member-remove-button"
                                            variant="ghost"
                                            size="sm"
                                            @click="confirmRemoveMember(member)"
                                        >
                                            <X class="h-4 w-4" />
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p>Remove member</p>
                                    </TooltipContent>
                                </Tooltip>
                            </TooltipProvider>

                            <Button
                                v-if="
                                    canManagePermissions &&
                                    member.role !== 'owner'
                                "
                                data-test="member-access-toggle"
                                variant="ghost"
                                size="sm"
                                @click="toggleAccessEditor(member)"
                            >
                                <ShieldCheck class="h-4 w-4" />
                            </Button>
                        </div>
                    </div>

                    <!-- What this member can do, and to which departments -->
                    <Form
                        v-if="editingAccessFor === member.id"
                        v-bind="updateMemberAccess.form([team.slug, member.id])"
                        class="mt-4 space-y-4 border-t pt-4"
                        data-test="member-access-form"
                        v-slot="{ errors, processing }"
                        @success="editingAccessFor = null"
                    >
                        <fieldset class="grid gap-2">
                            <legend class="text-sm font-medium">
                                Departments
                            </legend>
                            <p class="text-sm text-muted-foreground">
                                Their permissions come from the departments they
                                work in.
                            </p>

                            <p
                                v-if="departments.length === 0"
                                class="text-sm text-muted-foreground"
                            >
                                This team has no departments yet.
                            </p>

                            <label
                                v-for="department in departments"
                                :key="department.id"
                                class="flex items-center gap-2 text-sm"
                            >
                                <Checkbox
                                    name="department_ids[]"
                                    :value="department.id"
                                    :default-value="
                                        member.department_ids.includes(
                                            department.id,
                                        )
                                    "
                                />
                                {{ department.name }}
                            </label>
                            <InputError :message="errors.department_ids" />
                        </fieldset>

                        <div class="grid gap-2">
                            <Label :for="`scope-${member.id}`">
                                Data they can see
                            </Label>
                            <select
                                :id="`scope-${member.id}`"
                                name="data_scope"
                                :value="member.data_scope"
                                class="h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                            >
                                <option
                                    v-for="scope in dataScopes"
                                    :key="scope.value"
                                    :value="scope.value"
                                >
                                    {{ scope.label }}
                                </option>
                            </select>
                            <InputError :message="errors.data_scope" />
                        </div>

                        <div class="flex items-center gap-2">
                            <Button
                                type="submit"
                                size="sm"
                                :disabled="processing"
                            >
                                Save access
                            </Button>
                            <Button
                                type="button"
                                size="sm"
                                variant="ghost"
                                @click="editingAccessFor = null"
                            >
                                Cancel
                            </Button>
                        </div>
                    </Form>
                </div>
            </div>
        </div>

        <!-- Pending Invitations Section -->
        <div v-if="invitations.length > 0" class="space-y-6">
            <Heading
                variant="small"
                title="Pending invitations"
                description="Invitations that haven't been accepted yet"
            />

            <div class="space-y-3">
                <div
                    v-for="invitation in invitations"
                    :key="invitation.code"
                    data-test="invitation-row"
                    class="flex items-center justify-between rounded-lg border p-4"
                >
                    <div class="flex items-center gap-4">
                        <div
                            class="flex h-10 w-10 items-center justify-center rounded-full bg-muted"
                        >
                            <Mail class="h-5 w-5 text-muted-foreground" />
                        </div>
                        <div>
                            <div class="font-medium">
                                {{ invitation.email }}
                            </div>
                            <div class="text-sm text-muted-foreground">
                                {{ invitation.role_label }}
                            </div>
                        </div>
                    </div>

                    <TooltipProvider v-if="permissions.canCancelInvitation">
                        <Tooltip>
                            <TooltipTrigger as-child>
                                <Button
                                    data-test="invitation-cancel-button"
                                    variant="ghost"
                                    size="sm"
                                    @click="confirmCancelInvitation(invitation)"
                                >
                                    <X class="h-4 w-4" />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>
                                <p>Cancel invitation</p>
                            </TooltipContent>
                        </Tooltip>
                    </TooltipProvider>
                </div>
            </div>
        </div>

        <!-- Danger Zone -->
        <div
            v-if="permissions.canDeleteTeam && !team.isPersonal"
            class="space-y-6"
        >
            <Heading
                variant="small"
                title="Delete team"
                description="Permanently delete your team"
            />
            <div
                class="space-y-4 rounded-lg border border-red-100 bg-red-50 p-4 dark:border-red-200/10 dark:bg-red-700/10"
            >
                <div
                    class="relative space-y-0.5 text-red-600 dark:text-red-100"
                >
                    <p class="font-medium">Warning</p>
                    <p class="text-sm">
                        Please proceed with caution, this cannot be undone.
                    </p>
                </div>
                <Button
                    data-test="delete-team-button"
                    variant="destructive"
                    @click="deleteDialogOpen = true"
                    >Delete team</Button
                >
            </div>
        </div>
    </div>

    <InviteMemberModal
        v-if="permissions.canCreateInvitation"
        :team="team"
        :available-roles="availableRoles"
        :open="inviteDialogOpen"
        @update:open="inviteDialogOpen = $event"
    />

    <RemoveMemberModal
        :team="team"
        :member="memberToRemove"
        :open="removeMemberDialogOpen"
        @update:open="removeMemberDialogOpen = $event"
    />

    <CancelInvitationModal
        :team="team"
        :invitation="invitationToCancel"
        :open="cancelInvitationDialogOpen"
        @update:open="cancelInvitationDialogOpen = $event"
    />

    <DeleteTeamModal
        v-if="permissions.canDeleteTeam && !team.isPersonal"
        :team="team"
        :open="deleteDialogOpen"
        @update:open="deleteDialogOpen = $event"
    />
</template>
