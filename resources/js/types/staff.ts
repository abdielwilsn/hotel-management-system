export type StaffRole =
    | 'receptionist'
    | 'housekeeping'
    | 'accountant'
    | 'manager'
    | 'admin';

export type StaffStatus = 'active' | 'inactive' | 'on_leave';

export type StaffGender = 'male' | 'female' | 'other';

export interface StaffMember {
    id: number;
    full_name: string;
    email: string;
    phone?: string;
    role: StaffRole;
    status: StaffStatus;
    department_id: number;
    employment_date: string;
}

export interface DepartmentOption {
    id: number;
    name: string;
}

export interface Staff {
    id: number;
    full_name: string;
    email: string;
    phone?: string;
    address?: string;
    gender?: StaffGender;
    role: StaffRole;
    department_id: number;
    department?: DepartmentOption;
    employment_date: string;
    salary?: number;
    emergency_contact_name?: string;
    emergency_contact_phone?: string;
    profile_image_path?: string;
    status: StaffStatus;
}

export interface StaffForm {
    full_name: string;
    email: string;
    phone?: string;
    address?: string;
    gender?: StaffGender;
    role: StaffRole;
    department_id: number;
    employment_date: string;
    salary?: number;
    emergency_contact_name?: string;
    emergency_contact_phone?: string;
    status: StaffStatus;
}
