import { startStimulusApp } from '@symfony/stimulus-bundle';

const app = startStimulusApp();

window.Stimulus = app;

export default app;
