export type DepartmentStatus = 'active' | 'inactive';

export type DepartmentMember = {
    id: number;
    name: string;
    email: string;
};

export type Department = {
    id: number;
    name: string;
    description: string | null;
    status: DepartmentStatus;
    manager: DepartmentMember | null;
};

export type DepartmentForm = {
    name: string;
    description: string;
    status: DepartmentStatus;
    manager_id: number | '';
};
