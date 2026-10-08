<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import TicketController from '@/actions/App/Http/Controllers/TicketController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import type { SampleTicket } from '@/data/sampleTickets';
import { sampleTickets } from '@/data/sampleTickets';

const form = useForm({
    customer_email: '',
    subject: '',
    body: '',
});

const useSample = (sample: SampleTicket) => {
    form.clearErrors();
    Object.assign(form, sample);
};

// On success the server redirects to the new ticket's page.
const submit = () => form.submit(TicketController.store());
</script>

<template>
    <Card class="h-fit">
        <CardHeader>
            <CardTitle>New ticket</CardTitle>
            <CardDescription>
                Paste in a customer message. It's triaged in the background by a
                queued job.
            </CardDescription>
        </CardHeader>
        <CardContent>
            <form class="space-y-4" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="customer_email">Customer email</Label>
                    <Input
                        id="customer_email"
                        v-model="form.customer_email"
                        type="email"
                        placeholder="customer@example.com"
                    />
                    <InputError :message="form.errors.customer_email" />
                </div>
                <div class="grid gap-2">
                    <Label for="subject">Subject</Label>
                    <Input id="subject" v-model="form.subject" required />
                    <InputError :message="form.errors.subject" />
                </div>
                <div class="grid gap-2">
                    <Label for="body">Message</Label>
                    <Textarea id="body" v-model="form.body" rows="7" required />
                    <InputError :message="form.errors.body" />
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <Button type="submit" :disabled="form.processing">
                        <Spinner v-if="form.processing" />
                        Submit for triage
                    </Button>
                    <span class="text-xs text-muted-foreground">
                        or try a sample:
                    </span>
                    <Button
                        v-for="(sample, i) in sampleTickets"
                        :key="i"
                        type="button"
                        size="sm"
                        variant="outline"
                        @click="useSample(sample)"
                    >
                        {{ i + 1 }}
                    </Button>
                </div>
            </form>
        </CardContent>
    </Card>
</template>
