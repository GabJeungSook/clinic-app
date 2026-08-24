<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { Check, ChevronsUpDown, Search } from '@lucide/vue';

interface Option {
    value: string | number;
    label: string;
}

const props = withDefaults(
    defineProps<{
        modelValue: string | number | null;
        options: Option[];
        placeholder?: string;
        emptyLabel?: string; // when set, a selectable option that clears the value
        disabled?: boolean;
        sort?: boolean; // alphabetical by label
        searchThreshold?: number; // show the search box when options.length >= this
        id?: string;
    }>(),
    {
        placeholder: 'Select…',
        disabled: false,
        sort: true,
        searchThreshold: 8,
    },
);

const emit = defineEmits<{ 'update:modelValue': [value: string | number | null] }>();

const open = ref(false);
const query = ref('');
const highlighted = ref(0);
const triggerEl = ref<HTMLElement | null>(null);
const panelEl = ref<HTMLElement | null>(null);
const searchEl = ref<HTMLInputElement | null>(null);
const panelStyle = ref<Record<string, string>>({});

const sortedOptions = computed(() => {
    const opts = [...props.options];
    if (props.sort) opts.sort((a, b) => a.label.localeCompare(b.label));
    return opts;
});

const filtered = computed(() => {
    const q = query.value.trim().toLowerCase();
    return q ? sortedOptions.value.filter((o) => o.label.toLowerCase().includes(q)) : sortedOptions.value;
});

const showSearch = computed(() => props.options.length >= props.searchThreshold);

const selectedLabel = computed(() => {
    const o = props.options.find((x) => String(x.value) === String(props.modelValue));
    return o ? o.label : '';
});

function updatePosition() {
    const el = triggerEl.value;
    if (!el) return;
    const r = el.getBoundingClientRect();
    panelStyle.value = {
        position: 'fixed',
        top: `${r.bottom + 4}px`,
        left: `${r.left}px`,
        width: `${r.width}px`,
        zIndex: '60',
    };
}

function openMenu() {
    if (props.disabled) return;
    open.value = true;
    query.value = '';
    highlighted.value = 0;
    nextTick(() => {
        updatePosition();
        searchEl.value?.focus();
    });
}
function toggle() {
    open.value ? close() : openMenu();
}
function close() {
    open.value = false;
}
function selectOption(value: string | number | null) {
    emit('update:modelValue', value);
    close();
}

function onKeydown(e: KeyboardEvent) {
    if (!open.value) {
        if (e.key === 'Enter' || e.key === 'ArrowDown' || e.key === ' ') {
            e.preventDefault();
            openMenu();
        }
        return;
    }
    if (e.key === 'Escape') {
        close();
    } else if (e.key === 'ArrowDown') {
        e.preventDefault();
        highlighted.value = Math.min(highlighted.value + 1, filtered.value.length - 1);
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        highlighted.value = Math.max(highlighted.value - 1, 0);
    } else if (e.key === 'Enter') {
        e.preventDefault();
        const o = filtered.value[highlighted.value];
        if (o) selectOption(o.value);
    }
}

function onDocPointer(e: MouseEvent) {
    const t = e.target as Node;
    if (triggerEl.value?.contains(t) || panelEl.value?.contains(t)) return;
    close();
}

watch(open, (v) => {
    if (v) {
        document.addEventListener('mousedown', onDocPointer);
        window.addEventListener('scroll', updatePosition, true);
        window.addEventListener('resize', updatePosition);
    } else {
        document.removeEventListener('mousedown', onDocPointer);
        window.removeEventListener('scroll', updatePosition, true);
        window.removeEventListener('resize', updatePosition);
    }
});

onBeforeUnmount(() => {
    document.removeEventListener('mousedown', onDocPointer);
    window.removeEventListener('scroll', updatePosition, true);
    window.removeEventListener('resize', updatePosition);
});
</script>

<template>
    <div class="relative">
        <button
            :id="id"
            ref="triggerEl"
            type="button"
            :disabled="disabled"
            class="field flex items-center justify-between gap-2 text-left"
            :class="{ 'cursor-not-allowed opacity-50': disabled }"
            @click="toggle"
            @keydown="onKeydown"
        >
            <span class="truncate" :class="selectedLabel ? '' : 'text-muted-foreground'">
                {{ selectedLabel || placeholder }}
            </span>
            <ChevronsUpDown class="size-4 shrink-0 text-muted-foreground" />
        </button>

        <Teleport to="body">
            <div
                v-if="open"
                ref="panelEl"
                :style="panelStyle"
                class="overflow-hidden rounded-lg border bg-popover text-popover-foreground shadow-lg"
            >
                <div v-if="showSearch" class="relative border-b p-1.5">
                    <Search class="absolute left-3 top-3 size-4 text-muted-foreground" />
                    <input
                        ref="searchEl"
                        v-model="query"
                        placeholder="Search…"
                        class="h-8 w-full rounded-md bg-transparent pl-8 pr-2 text-sm outline-none"
                        @keydown="onKeydown"
                    />
                </div>
                <ul class="max-h-60 overflow-y-auto p-1">
                    <li v-if="emptyLabel">
                        <button
                            type="button"
                            class="flex w-full items-center justify-between rounded-md px-2 py-1.5 text-left text-sm hover:bg-accent"
                            @click="selectOption('')"
                        >
                            <span class="text-muted-foreground">{{ emptyLabel }}</span>
                            <Check v-if="!modelValue" class="size-4 text-primary" />
                        </button>
                    </li>
                    <li v-for="(o, i) in filtered" :key="o.value">
                        <button
                            type="button"
                            class="flex w-full items-center justify-between rounded-md px-2 py-1.5 text-left text-sm hover:bg-accent"
                            :class="{ 'bg-accent': i === highlighted }"
                            @click="selectOption(o.value)"
                            @mousemove="highlighted = i"
                        >
                            <span class="truncate">{{ o.label }}</span>
                            <Check v-if="String(o.value) === String(modelValue)" class="size-4 shrink-0 text-primary" />
                        </button>
                    </li>
                    <li v-if="filtered.length === 0" class="px-2 py-4 text-center text-sm text-muted-foreground">
                        No matches.
                    </li>
                </ul>
            </div>
        </Teleport>
    </div>
</template>
