<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Building2, Calendar, Users, BarChart3 } from 'lucide-vue-next';
import { computed } from 'vue';
import { dashboard, login, register } from '@/routes';
import { Button } from '@/components/ui/button';

withDefaults(
    defineProps<{
        canRegister: boolean;
    }>(),
    {
        canRegister: true,
    },
);

const page = usePage();
const dashboardUrl = computed(() =>
    page.props.currentTeam ? dashboard(page.props.currentTeam.slug).url : '/',
);

const features = [
    {
        icon: Building2,
        title: 'Property Management',
        description: 'Manage multiple properties with ease',
    },
    {
        icon: Calendar,
        title: 'Room Bookings',
        description: 'Streamlined reservation system',
    },
    {
        icon: Users,
        title: 'Staff Management',
        description: 'Organize teams and departments',
    },
    {
        icon: BarChart3,
        title: 'Invoicing & Analytics',
        description: 'Track revenue and occupancy rates',
    },
];
</script>

<template>
    <Head title="Hotel Management System" />
    
    <!-- Navigation -->
    <nav class="fixed top-0 w-full bg-white/80 backdrop-blur-md border-b border-gray-200 dark:bg-gray-900/80 dark:border-gray-800 z-50">
        <div class="max-w-7xl mx-auto px-6 lg:px-8 py-4 flex items-center justify-between">
            <Link :href="'/'" class="flex items-center gap-2">
                <div class="h-8 w-8 bg-gradient-to-br from-teal-600 to-teal-800 rounded-lg flex items-center justify-center">
                    <Building2 class="h-5 w-5 text-white" />
                </div>
                <span class="font-semibold text-lg text-gray-900 dark:text-white">HMS</span>
            </Link>
            
            <div class="flex items-center gap-4">
                <Link
                    v-if="$page.props.auth.user"
                    :href="dashboardUrl"
                >
                    <Button>Dashboard</Button>
                </Link>
                <template v-else>
                    <Link :href="login()">
                        <Button variant="ghost">Log in</Button>
                    </Link>
                    <Link v-if="canRegister" :href="register()">
                        <Button>Register</Button>
                    </Link>
                </template>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <div class="relative min-h-screen bg-gradient-to-b from-gray-50 to-white dark:from-gray-900 dark:to-gray-800 pt-24">
        <!-- Background decoration -->
        <div class="absolute inset-0 overflow-hidden">
            <div class="absolute -top-40 -right-40 w-80 h-80 bg-teal-100 rounded-full mix-blend-multiply filter blur-3xl opacity-20 dark:opacity-10 animate-pulse"></div>
            <div class="absolute -bottom-40 -left-40 w-80 h-80 bg-orange-100 rounded-full mix-blend-multiply filter blur-3xl opacity-20 dark:opacity-10 animate-pulse" style="animation-delay: 2s"></div>
        </div>

        <div class="relative max-w-7xl mx-auto px-6 lg:px-8 py-20">
            <!-- Main heading -->
            <div class="text-center mb-16">
                <h1 class="text-5xl lg:text-6xl font-bold tracking-tight text-gray-900 dark:text-white mb-6">
                    Modern Hotel
                    <span class="bg-gradient-to-r from-teal-600 to-orange-500 bg-clip-text text-transparent">
                        Management
                    </span>
                </h1>
                <p class="text-xl text-gray-600 dark:text-gray-400 max-w-2xl mx-auto mb-8">
                    Streamline your entire hotel operations with our comprehensive management system. From bookings to invoicing, we've got you covered.
                </p>
                
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <Link v-if="$page.props.auth.user" :href="dashboardUrl">
                        <Button size="lg" class="bg-teal-600 hover:bg-teal-700">
                            Go to Dashboard
                        </Button>
                    </Link>
                    <Link v-else :href="login()">
                        <Button size="lg" class="bg-teal-600 hover:bg-teal-700">
                            Log In
                        </Button>
                    </Link>
                    <Link v-if="canRegister && !$page.props.auth.user" :href="register()">
                        <Button size="lg" variant="outline">
                            Create Account
                        </Button>
                    </Link>
                </div>
            </div>

            <!-- Features Grid -->
            <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-8 mt-20">
                <div
                    v-for="(feature, index) in features"
                    :key="index"
                    class="group bg-white dark:bg-gray-800 rounded-xl p-6 shadow-sm hover:shadow-lg transition-all duration-300 border border-gray-200 dark:border-gray-700"
                >
                    <div class="mb-4 p-3 bg-teal-50 dark:bg-teal-900/20 rounded-lg w-fit">
                        <component
                            :is="feature.icon"
                            class="h-6 w-6 text-teal-600 dark:text-teal-400"
                        />
                    </div>
                    <h3 class="font-semibold text-gray-900 dark:text-white mb-2">
                        {{ feature.title }}
                    </h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        {{ feature.description }}
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-gray-900 dark:bg-black text-gray-400 py-12">
        <div class="max-w-7xl mx-auto px-6 lg:px-8 text-center">
            <p>&copy; 2026 Hotel Management System. All rights reserved.</p>
        </div>
    </footer>
</template>
