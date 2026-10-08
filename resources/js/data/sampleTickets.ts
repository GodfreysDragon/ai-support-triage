/**
 * Demo tickets for the "try a sample" buttons on the ticket form. They cover
 * an urgent bug, an annoyed billing request and a friendly feature request,
 * so each one triages differently.
 */
export const sampleTickets = [
    {
        customer_email: 'ops@acme.test',
        subject: 'Dashboard returns 500 since this morning',
        body: 'Since about 9am every page in the dashboard returns a 500 error for our whole team. We have a board review at 2pm and cannot export anything. Please help ASAP.',
    },
    {
        customer_email: 'finance@globex.test',
        subject: 'Charged twice for September',
        body: 'We were billed twice for our September invoice (INV-2291). Can you refund the duplicate charge? This is the second time this has happened, which is pretty frustrating.',
    },
    {
        customer_email: 'sam@initech.test',
        subject: 'Can we schedule recurring exports?',
        body: 'Love the product! Is there a way to schedule the CSV export to run every Monday and email it to my team? If not, that would be a great feature.',
    },
];

export type SampleTicket = (typeof sampleTickets)[number];
