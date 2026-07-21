export type TeamRole = 'owner' | 'admin' | 'member' | 'pos';

export type Team = {
    id: number;
    name: string;
    slug: string;
    isPersonal: boolean;
    role?: TeamRole;
    roleLabel?: string;
    isCurrent?: boolean;
    currency?: string;
    locale?: string;
};

export type DataScope = 'all' | 'departments';

export type TeamMember = {
    id: number;
    name: string;
    email: string;
    avatar?: string | null;
    role: TeamRole;
    role_label: string;
    data_scope: DataScope;
    department_ids: number[];
};

export type AbilityOption = {
    value: string;
    label: string;
};

export type AbilityGroup = {
    group: string;
    abilities: AbilityOption[];
};

export type DataScopeOption = {
    value: DataScope;
    label: string;
};

export type TeamInvitation = {
    code: string;
    email: string;
    role: TeamRole;
    role_label: string;
    created_at: string;
};

export type TeamPermissions = {
    canUpdateTeam: boolean;
    canDeleteTeam: boolean;
    canAddMember: boolean;
    canUpdateMember: boolean;
    canRemoveMember: boolean;
    canCreateInvitation: boolean;
    canCancelInvitation: boolean;
};

export type RoleOption = {
    value: TeamRole;
    label: string;
};
