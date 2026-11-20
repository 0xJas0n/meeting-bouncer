import './stimulus_bootstrap.js';
import './styles/app.css';

// Turbo navigations skip the first-load deal animation, so view transitions snapshot solid paper.
document.addEventListener('turbo:visit', () => document.documentElement.classList.add('turbo-nav'));
import './paper.js';
