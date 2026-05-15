import gsap from 'gsap';

gsap.config({ force3D: true });

if (typeof window !== 'undefined') {
    window.gsap = gsap;
}

export default gsap;
