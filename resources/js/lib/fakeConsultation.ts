export type DetailLevel = 'Brief' | 'Normal' | 'Detailed';

export interface TranscriptTurn {
    who: string;
    text: string;
}

export interface SummarySection {
    heading: string;
    body: string;
}

export const detailLevels: DetailLevel[] = ['Brief', 'Normal', 'Detailed'];

export const levelHints: Record<DetailLevel, string> = {
    Brief: 'Brief keeps each section to a few lines.',
    Normal: 'Normal covers the discussion, decisions and follow-up in full sentences.',
    Detailed: 'Detailed keeps the reasoning and the safety netting wording.',
};

export const fakeTranscript: TranscriptTurn[] = [
    {
        who: 'Speaker 1',
        text: 'Thanks for coming in. How has the chest been since we changed the inhaler?',
    },
    {
        who: 'Speaker 2',
        text: 'Better in the day, but I still wake up coughing two or three nights a week.',
    },
    {
        who: 'Speaker 1',
        text: 'And the reliever, how often are you reaching for it?',
    },
    {
        who: 'Speaker 2',
        text: 'Maybe four times a week. More if I have been walking uphill.',
    },
    {
        who: 'Speaker 1',
        text: 'Your peak flow today is 380, which is up from 340 in June. Chest is clear on listening.',
    },
    {
        who: 'Speaker 2',
        text: 'I did wonder whether the new one is the right dose.',
    },
    {
        who: 'Speaker 1',
        text: 'I would like to keep the same inhaler but step the preventer up to two puffs twice a day, and I will check your technique now.',
    },
    { who: 'Speaker 2', text: 'That is fine. Do I need to come back?' },
    {
        who: 'Speaker 1',
        text: 'Six weeks, and keep a note of the night-time symptoms. If the reliever goes above three times a week before then, ring the surgery.',
    },
];

export const fakeSummaries: Record<DetailLevel, SummarySection[]> = {
    Brief: [
        {
            heading: 'What we discussed',
            body: 'Asthma review. Daytime symptoms improved on the new inhaler. Night-time cough two to three times a week. Reliever used around four times a week. Peak flow 380, up from 340 in June. Chest clear.',
        },
        {
            heading: 'Decisions',
            body: 'Continue the same inhaler. Step the preventer up to two puffs twice daily. Inhaler technique checked in clinic.',
        },
        {
            heading: 'Next steps',
            body: 'Review in six weeks. Keep a note of night-time symptoms. Ring the surgery if reliever use goes above three times a week.',
        },
    ],
    Normal: [
        {
            heading: 'What we discussed',
            body: 'Follow-up asthma review after the inhaler change. Daytime breathing has improved, but the patient still wakes with a cough two to three nights a week. Reliever use is around four times a week, rising with exertion such as walking uphill. Peak flow measured 380 today, up from 340 in June. Chest was clear on auscultation. The patient asked whether the current dose is right.',
        },
        {
            heading: 'Decisions',
            body: 'Stay on the same inhaler rather than switching again. Step the preventer up to two puffs twice daily. Inhaler technique was checked during the consultation. No further investigations arranged at this point.',
        },
        {
            heading: 'Next steps',
            body: 'Review appointment in six weeks. The patient will keep a note of night-time symptoms between now and then. If reliever use rises above three times a week before the review, the patient will ring the surgery.',
        },
    ],
    Detailed: [
        {
            heading: 'What we discussed',
            body: 'Follow-up asthma review, arranged after the preventer inhaler was changed at the June appointment. The patient reports daytime breathing is clearly better, with the main remaining problem being a night-time cough that wakes them two to three times a week. Reliever use is about four times a week, and higher on days involving exertion, specifically walking uphill. Peak flow today was 380, compared with 340 in June. On examination the chest was clear. The patient raised their own question about whether the dose of the current inhaler is correct, rather than the choice of inhaler.',
        },
        {
            heading: 'Decisions',
            body: 'Agreed to continue with the same inhaler rather than change device again, on the basis that daytime control has improved. The preventer dose is stepped up to two puffs twice daily. Inhaler technique was observed and checked in the consultation. No blood tests, imaging or referral were considered necessary today. The patient agreed with the plan when it was put to them.',
        },
        {
            heading: 'Next steps',
            body: 'Face-to-face review in six weeks to reassess night-time symptoms, reliever use and peak flow. The patient will keep a written note of night-time symptoms to bring to that appointment. Safety netting: if reliever use rises above three times a week before the review, or symptoms worsen, the patient will ring the surgery rather than wait.',
        },
    ],
};
