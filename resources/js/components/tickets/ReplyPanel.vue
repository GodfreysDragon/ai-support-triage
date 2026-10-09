<script setup lang="ts">
import { Check, Copy, Sparkles, Square, Undo2, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import AlertError from '@/components/AlertError.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useReplyDraft } from '@/composables/useReplyDraft';
import type { Ticket } from '@/types';

const props = defineProps<{
    ticket: Ticket;
}>();

const guidance = ref('');

const {
    draft,
    error,
    copyState,
    stopped,
    canRestore,
    busy,
    isFetching,
    isStreaming,
    generate,
    stop,
    restore,
    copy,
} = useReplyDraft(() => props.ticket);

const copyButton = computed(
    () =>
        ({
            idle: { icon: Copy, label: 'Copy' },
            copied: { icon: Check, label: 'Copied' },
            failed: { icon: X, label: 'Copy failed' },
        })[copyState.value],
);
</script>

<template>
    <Card class="h-fit">
        <CardHeader>
            <CardTitle>Draft a reply</CardTitle>
            <CardDescription>
                AI writes a first draft. Review it before sending.
            </CardDescription>
        </CardHeader>
        <CardContent class="space-y-4">
            <div class="grid gap-2">
                <Label for="guidance">Guidance (optional)</Label>
                <Textarea
                    id="guidance"
                    v-model="guidance"
                    rows="2"
                    maxlength="1000"
                    placeholder="e.g. Offer a 20% credit and keep it short"
                    :disabled="busy"
                />
            </div>

            <div class="flex flex-wrap gap-2">
                <Button v-if="!busy" @click="generate(guidance)">
                    <Sparkles />
                    {{ draft ? 'Regenerate' : 'Generate reply' }}
                </Button>
                <Button v-else variant="outline" @click="stop">
                    <Square /> Stop
                </Button>
                <Button v-if="canRestore" variant="outline" @click="restore">
                    <Undo2 /> Restore saved draft
                </Button>
                <Button v-if="draft && !busy" variant="ghost" @click="copy">
                    <component :is="copyButton.icon" />
                    {{ copyButton.label }}
                </Button>
            </div>

            <p v-if="stopped && draft" class="text-sm text-muted-foreground">
                Stopped. This partial draft isn't saved.
            </p>

            <AlertError
                v-if="error"
                :errors="[error]"
                title="Couldn't draft a reply"
            />

            <div
                class="min-h-48 rounded-md border bg-muted/30 p-4 text-sm whitespace-pre-wrap"
                aria-live="polite"
            >
                <span v-if="draft">{{ draft }}</span>
                <span v-else-if="isFetching" class="text-muted-foreground">
                    Thinking…
                </span>
                <span v-else class="text-muted-foreground">
                    The drafted reply will appear here.
                </span>
                <span
                    v-if="isStreaming"
                    class="ml-0.5 inline-block h-4 w-1.5 animate-pulse bg-foreground align-text-bottom"
                />
            </div>
        </CardContent>
    </Card>
</template>
