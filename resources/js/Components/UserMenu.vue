<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { Menu, MenuButton, MenuItem, MenuItems } from '@headlessui/vue';
import { ChevronDownIcon, UserCircleIcon, ArrowRightStartOnRectangleIcon } from '@heroicons/vue/24/outline';

const user = computed(() => usePage().props.auth.user);
const initials = computed(() =>
    (user.value?.name ?? '?').split(' ').map((p) => p[0]).slice(0, 2).join('').toUpperCase(),
);
</script>

<template>
    <Menu as="div" class="relative">
        <MenuButton class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white py-1.5 pl-1.5 pr-3 text-left transition hover:border-slate-300 focus:outline-none focus-visible:ring-4 focus-visible:ring-secondary-100">
            <span class="grid size-9 place-items-center rounded-lg bg-secondary text-sm font-semibold text-tertiary">{{ initials }}</span>
            <span class="hidden leading-tight sm:block">
                <span class="block max-w-40 truncate text-sm font-semibold text-slate-900">{{ user.name }}</span>
                <span class="block max-w-40 truncate text-xs text-slate-500">{{ user.type_label }}</span>
            </span>
            <ChevronDownIcon class="size-4 text-slate-400" />
        </MenuButton>
        <transition
            enter-active-class="transition duration-100 ease-out" enter-from-class="scale-95 opacity-0" enter-to-class="scale-100 opacity-100"
            leave-active-class="transition duration-75 ease-in" leave-from-class="scale-100 opacity-100" leave-to-class="scale-95 opacity-0"
        >
            <MenuItems class="absolute right-0 z-40 mt-2 w-56 origin-top-right rounded-xl border border-slate-100 bg-white p-1.5 shadow-xl focus:outline-none">
                <div class="border-b border-slate-100 px-3 pb-2.5 pt-1.5">
                    <p class="truncate text-sm font-semibold text-slate-900">{{ user.name }}</p>
                    <p class="truncate text-xs text-slate-500">{{ user.email }}</p>
                </div>
                <MenuItem v-slot="{ active }">
                    <Link :href="route('profile.edit')" :class="['mt-1 flex items-center gap-2 rounded-lg px-3 py-2 text-sm', active ? 'bg-slate-50 text-slate-900' : 'text-slate-600']">
                        <UserCircleIcon class="size-4" /> My Profile
                    </Link>
                </MenuItem>
                <MenuItem v-slot="{ active }">
                    <Link :href="route('logout')" method="post" as="button" :class="['flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm', active ? 'bg-rose-50 text-rose-700' : 'text-slate-600']">
                        <ArrowRightStartOnRectangleIcon class="size-4" /> Log out
                    </Link>
                </MenuItem>
            </MenuItems>
        </transition>
    </Menu>
</template>
