<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { Check, ChevronsUpDown, Plus, Users } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import CreateTeamModal from '@/components/CreateTeamModal.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { switchMethod } from '@/routes/teams';
import type { Team } from '@/types';

const props = withDefaults(
    defineProps<{
        inHeader?: boolean;
    }>(),
    {
        inHeader: false,
    },
);

const page = usePage();
const isMobile = ref(false);
let mediaQuery: MediaQueryList | null = null;
const updateIsMobile = () => {
    if (mediaQuery) {
        isMobile.value = mediaQuery.matches;
    }
};

const currentTeam = computed(() => page.props.currentTeam);
const teams = computed(() => page.props.teams ?? []);
const menuContentClass = computed(() =>
    props.inHeader
        ? 'w-56'
        : 'w-[--reka-dropdown-menu-trigger-width] min-w-56 rounded-lg',
);
const teamItemClass = computed(() =>
    props.inHeader ? 'cursor-pointer gap-2' : 'cursor-pointer gap-2 p-2',
);
const checkIconClass = computed(() =>
    props.inHeader ? 'ml-auto size-4' : 'ml-auto h-4 w-4',
);
const plusIconClass = computed(() => (props.inHeader ? 'size-4' : 'h-4 w-4'));

const switchTeam = (team: Team) => {
    const previousTeamSlug = currentTeam.value?.slug;

    router.visit(switchMethod(team.slug), {
        onFinish: () => {
            if (!previousTeamSlug || typeof window === 'undefined') {
                router.reload();

                return;
            }

            const currentUrl = `${window.location.pathname}${window.location.search}${window.location.hash}`;
            const segment = `/${previousTeamSlug}`;

            if (currentUrl.includes(segment)) {
                router.visit(currentUrl.replace(segment, `/${team.slug}`), {
                    replace: true,
                });

                return;
            }

            router.reload();
        },
    });
};

onMounted(() => {
    mediaQuery = window.matchMedia('(max-width: 767px)');
    updateIsMobile();
    mediaQuery.addEventListener('change', updateIsMobile);
});

onUnmounted(() => {
    mediaQuery?.removeEventListener('change', updateIsMobile);
});
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button
                data-test="team-switcher-trigger"
                variant="ghost"
                :class="[
                    props.inHeader
                        ? 'h-8 gap-1 px-2'
                        : 'w-full justify-start rounded-md px-3 py-2.5 transition-all duration-200 hover:bg-sidebar-accent/70 has-[>svg]:px-3 data-[state=open]:bg-gradient-to-r data-[state=open]:from-sidebar-primary/15 data-[state=open]:to-sidebar-primary/5 data-[state=open]:text-sidebar-primary',
                ]"
            >
                <Users
                    :class="[
                        'size-5 flex-shrink-0 shrink-0 transition-colors duration-200',
                        props.inHeader
                            ? 'hidden'
                            : 'hidden text-sidebar-primary/80 group-data-[collapsible=icon]:block',
                    ]"
                />
                <div
                    :class="[
                        'grid flex-1 text-left text-sm leading-tight',
                        props.inHeader
                            ? 'max-w-[100px] truncate'
                            : 'group-data-[collapsible=icon]:hidden',
                    ]"
                >
                    <span
                        :class="[
                            'truncate font-semibold text-sidebar-foreground',
                            props.inHeader ? 'text-xs font-medium' : '',
                        ]"
                    >
                        {{ currentTeam?.name ?? 'Select team' }}
                    </span>
                </div>
                <ChevronsUpDown
                    :class="[
                        'size-4 opacity-50 transition-transform duration-200 group-data-[state=open]:rotate-180',
                        props.inHeader
                            ? 'opacity-50'
                            : 'ml-auto group-data-[collapsible=icon]:hidden',
                    ]"
                />
            </Button>
        </DropdownMenuTrigger>

        <DropdownMenuContent
            :class="[
                menuContentClass,
                'border border-sidebar-border/40 shadow-lg',
            ]"
            :side="props.inHeader ? undefined : isMobile ? 'bottom' : 'right'"
            :align="props.inHeader ? 'end' : 'start'"
            :side-offset="props.inHeader ? undefined : 8"
        >
            <DropdownMenuLabel
                class="text-xs font-semibold tracking-wider text-sidebar-foreground/60 uppercase"
            >
                Teams
            </DropdownMenuLabel>
            <DropdownMenuItem
                v-for="team in teams"
                :key="team.id"
                data-test="team-switcher-item"
                :class="[
                    teamItemClass,
                    'rounded-md transition-all duration-150',
                    currentTeam?.id === team.id
                        ? 'bg-sidebar-primary/10 font-medium text-sidebar-primary'
                        : 'hover:bg-sidebar-accent',
                ]"
                @click="switchTeam(team)"
            >
                <span class="flex-1">{{ team.name }}</span>
                <Check
                    v-if="currentTeam?.id === team.id"
                    :class="[checkIconClass, 'text-sidebar-primary']"
                />
            </DropdownMenuItem>
            <DropdownMenuSeparator class="my-1" />
            <CreateTeamModal>
                <DropdownMenuItem
                    data-test="team-switcher-new-team"
                    :class="[
                        teamItemClass,
                        'rounded-md transition-all duration-150 hover:bg-sidebar-accent',
                    ]"
                    @select.prevent
                >
                    <Plus :class="[plusIconClass, 'text-sidebar-primary/70']" />
                    <span class="font-medium text-sidebar-foreground/60"
                        >New team</span
                    >
                </DropdownMenuItem>
            </CreateTeamModal>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
