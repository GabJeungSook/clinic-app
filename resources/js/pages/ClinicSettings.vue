<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, computed, onUnmounted } from 'vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import { ArrowUpCircle, DatabaseBackup, Download, RefreshCw, RotateCcw, RotateCw, Upload } from '@lucide/vue';

defineOptions({ layout: { breadcrumbs: [{ title: 'Settings', href: '/clinic-settings' }] } });

type UpdateStatus = {
    state: 'idle' | 'checking' | 'available' | 'downloading' | 'downloaded' | 'not-available' | 'error';
    version: string | null;
    percent: number;
    notes: string | null;
    message: string | null;
    checked_at: string | null;
};

const props = defineProps<{
    settings: Record<string, string | number | boolean | null>;
    backups: Array<{ name: string; size_kb: number }>;
    lastBackup: string | null;
    updater: { enabled: boolean; currentVersion: string; status: UpdateStatus };
}>();

// --- Software update -------------------------------------------------------
// The check/download run in the Electron shell and report progress back as
// events; we poll a tiny status endpoint while something is in flight.
const status = ref<UpdateStatus>(props.updater.status);
let poll: ReturnType<typeof setInterval> | null = null;

const busy = computed(() => ['checking', 'downloading'].includes(status.value.state));
const checking = computed(() => status.value.state === 'checking');

const stopPolling = () => {
    if (poll) { clearInterval(poll); poll = null; }
};
const pollStatus = async () => {
    try {
        const res = await fetch('/clinic-settings/update/status', { headers: { Accept: 'application/json' } });
        if (res.ok) {
            status.value = await res.json();
            if (!['checking', 'downloading'].includes(status.value.state)) stopPolling();
        }
    } catch {
        /* offline mid-poll — keep the last known state */
    }
};
const startPolling = () => { stopPolling(); poll = setInterval(pollStatus, 1500); };

const checkForUpdates = () => {
    status.value = { ...status.value, state: 'checking', message: null };
    router.post('/clinic-settings/update/check', {}, { preserveScroll: true, preserveState: true, onSuccess: startPolling });
};
const downloadUpdate = () => {
    status.value = { ...status.value, state: 'downloading', percent: 0 };
    router.post('/clinic-settings/update/download', {}, { preserveScroll: true, preserveState: true, onSuccess: startPolling });
};
const installUpdate = () => {
    router.post('/clinic-settings/update/install', {}, { preserveScroll: true, preserveState: true });
};

onUnmounted(stopPolling);

const form = useForm({
    clinic_name: (props.settings['clinic.name'] as string) ?? '',
    clinic_address: (props.settings['clinic.address'] as string) ?? '',
    clinic_phone: (props.settings['clinic.phone'] as string) ?? '',
    receipt_footer: (props.settings['clinic.receipt_footer'] as string) ?? '',
    currency: (props.settings['billing.currency'] as string) ?? 'PHP',
    currency_symbol: (props.settings['billing.currency_symbol'] as string) ?? '₱',
    tax_enabled: !!props.settings['billing.tax_enabled'],
    tax_rate: (props.settings['billing.tax_rate'] as number) ?? 12,
    tax_inclusive: !!props.settings['billing.tax_inclusive'],
    expiry_threshold_days: (props.settings['inventory.expiry_threshold_days'] as number) ?? 30,
});

const save = () => form.put('/clinic-settings', { preserveScroll: true });
const backupNow = () => router.post('/clinic-settings/backup', {}, { preserveScroll: true });
const restore = (name: string) => router.post('/clinic-settings/restore', { name }, { preserveScroll: true });

const importFile = ref<HTMLInputElement | null>(null);
const pickImport = () => importFile.value?.click();
const onImport = (e: Event) => {
    const file = (e.target as HTMLInputElement).files?.[0];
    if (!file) return;
    router.post('/clinic-settings/restore', { file }, { forceFormData: true });
    (e.target as HTMLInputElement).value = '';
};
</script>

<template>
    <Head title="Settings" />
    <div class=" grid w-full gap-6 p-4 md:grid-cols-2 md:p-6">
        <Card class="md:col-span-2">
            <CardHeader><CardTitle class="flex items-center gap-2"><ArrowUpCircle class="size-4" /> Software update</CardTitle></CardHeader>
            <CardContent class="space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm text-muted-foreground">
                        Current version: <strong>{{ updater.currentVersion }}</strong>
                    </p>
                    <Button variant="secondary" :disabled="busy" @click="checkForUpdates">
                        <RefreshCw class="size-4" :class="{ 'animate-spin': checking }" />
                        {{ checking ? 'Checking…' : 'Check for updates' }}
                    </Button>
                </div>

                <p v-if="status.state === 'not-available'" class="text-sm font-medium text-emerald-600">
                    You're on the latest version.
                </p>

                <div v-else-if="status.state === 'available'" class="space-y-3 rounded-lg border p-3">
                    <p class="text-sm font-medium">Version {{ status.version }} is available.</p>
                    <p v-if="status.notes" class="max-h-40 overflow-auto whitespace-pre-line text-xs text-muted-foreground">{{ status.notes }}</p>
                    <Button @click="downloadUpdate"><Download class="size-4" /> Download &amp; install</Button>
                </div>

                <div v-else-if="status.state === 'downloading'" class="space-y-2">
                    <p class="text-sm">Downloading version {{ status.version }}… {{ status.percent }}%</p>
                    <div class="h-2 w-full overflow-hidden rounded-full bg-muted">
                        <div class="h-full rounded-full bg-primary transition-all" :style="{ width: status.percent + '%' }"></div>
                    </div>
                </div>

                <div v-else-if="status.state === 'downloaded'" class="space-y-3 rounded-lg border border-primary/30 bg-primary/5 p-3">
                    <p class="text-sm font-medium">Version {{ status.version }} is ready to install.</p>
                    <p class="text-xs text-muted-foreground">The app will close and reopen to finish updating.</p>
                    <ConfirmDialog
                        title="Restart to update now?"
                        description="The app will close and reopen on the new version. Make sure no one is in the middle of a transaction."
                        confirm-text="Restart &amp; update"
                        @confirm="installUpdate"
                    >
                        <Button><RotateCw class="size-4" /> Restart to update</Button>
                    </ConfirmDialog>
                </div>

                <p v-else-if="status.state === 'error'" class="text-sm text-amber-600">
                    {{ status.message || 'Something went wrong checking for updates.' }}
                </p>
            </CardContent>
        </Card>

        <Card>
            <CardHeader><CardTitle>Clinic &amp; billing</CardTitle></CardHeader>
            <CardContent>
                <form class="grid gap-4" @submit.prevent="save">
                    <div class="grid gap-1.5"><Label>Clinic name</Label><Input v-model="form.clinic_name" /></div>
                    <div class="grid gap-1.5"><Label>Address</Label><Input v-model="form.clinic_address" /></div>
                    <div class="grid gap-1.5"><Label>Phone</Label><Input v-model="form.clinic_phone" /></div>
                    <div class="grid gap-1.5"><Label>Receipt footer</Label><Input v-model="form.receipt_footer" /></div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-1.5"><Label>Currency</Label><Input v-model="form.currency" /></div>
                        <div class="grid gap-1.5"><Label>Symbol</Label><Input v-model="form.currency_symbol" /></div>
                    </div>

                    <div class="rounded-lg border p-3">
                        <label class="flex items-center gap-2 text-sm font-medium"><input type="checkbox" v-model="form.tax_enabled" /> Apply tax</label>
                        <div class="mt-3 grid grid-cols-2 gap-4">
                            <div class="grid gap-1.5"><Label>Tax rate %</Label><Input type="number" step="0.001" v-model="form.tax_rate" /></div>
                            <label class="mt-6 flex items-center gap-2 text-sm"><input type="checkbox" v-model="form.tax_inclusive" /> Prices tax-inclusive</label>
                        </div>
                    </div>

                    <div class="grid gap-1.5"><Label>Expiry alert threshold (days)</Label><Input type="number" v-model="form.expiry_threshold_days" /></div>
                    <Button type="submit" :disabled="form.processing">Save settings</Button>
                </form>
            </CardContent>
        </Card>

        <Card>
            <CardHeader><CardTitle class="flex items-center gap-2"><DatabaseBackup class="size-4" /> Backups</CardTitle></CardHeader>
            <CardContent class="space-y-3">
                <p class="text-sm text-muted-foreground">
                    Last successful backup: <strong>{{ lastBackup ? lastBackup.slice(0, 19).replace('T', ' ') : 'never' }}</strong>
                </p>
                <div class="flex flex-wrap gap-2">
                    <Button variant="secondary" @click="backupNow"><DatabaseBackup class="size-4" /> Back up now</Button>
                    <input ref="importFile" type="file" accept=".sqlite" class="hidden" @change="onImport" />
                    <ConfirmDialog
                        title="Import & restore from a file?"
                        description="Choose a .sqlite backup to replace ALL current data with it. A safety backup of the current data is taken first, and you'll be signed out afterwards."
                        confirm-text="Choose file…"
                        @confirm="pickImport"
                    >
                        <Button variant="outline"><Upload class="size-4" /> Import from file…</Button>
                    </ConfirmDialog>
                </div>
                <ul class="divide-y divide-border text-sm">
                    <li v-for="b in backups" :key="b.name" class="flex items-center justify-between gap-2 py-1.5">
                        <span class="min-w-0 flex-1 truncate font-mono text-xs">{{ b.name }}</span>
                        <span class="shrink-0 text-muted-foreground">{{ b.size_kb }} KB</span>
                        <Button as-child variant="ghost" size="icon-sm" title="Download">
                            <a :href="`/clinic-settings/backup/${b.name}/download`"><Download class="size-4" /></a>
                        </Button>
                        <ConfirmDialog
                            title="Restore this backup?"
                            :description="`This replaces ALL current data with the snapshot '${b.name}'. A safety backup of the current data is taken first, and you'll be signed out afterwards.`"
                            confirm-text="Restore"
                            @confirm="restore(b.name)"
                        >
                            <Button variant="ghost" size="icon-sm" title="Restore"><RotateCcw class="size-4 text-amber-600" /></Button>
                        </ConfirmDialog>
                    </li>
                    <li v-if="backups.length === 0" class="py-3 text-center text-muted-foreground">No backups yet.</li>
                </ul>
                <p class="text-xs text-muted-foreground">Backups are stored in <code>storage/app/backups</code>. Use <strong>Download</strong> to copy one onto a USB drive — this PC holds the only data. <strong>Restore</strong> overwrites everything with the chosen snapshot.</p>
            </CardContent>
        </Card>
    </div>
</template>
