const getNavigator = () => (typeof navigator === 'undefined' ? {} : navigator);

export const getDeviceSignals = () => {
    const nav = getNavigator();
    const ua = nav.userAgent || '';
    const platform = nav.platform || '';
    const maxTouchPoints = Number(nav.maxTouchPoints || 0);

    return {
        ua,
        platform,
        maxTouchPoints,
        hasTouch: maxTouchPoints > 0 || 'ontouchstart' in window,
        hasHoverMouse: window.matchMedia('(hover: hover) and (pointer: fine)').matches,
        hasCoarsePointer: window.matchMedia('(pointer: coarse)').matches,
        isLargeViewport: window.matchMedia('(min-width: 768px)').matches,
    };
};

export const isTabletLikeDevice = () => {
    const {
        ua,
        platform,
        maxTouchPoints,
        hasCoarsePointer,
        isLargeViewport,
    } = getDeviceSignals();

    const iPadClassic = /iPad/i.test(ua);
    const iPadDesktopUa = platform === 'MacIntel' && maxTouchPoints > 1;
    const androidTablet = /Android/i.test(ua) && !/Mobile/i.test(ua);
    const tabletUa = /Tablet|Silk|Kindle|PlayBook/i.test(ua);
    const largeTouch = maxTouchPoints > 0 && isLargeViewport && hasCoarsePointer;

    return iPadClassic || iPadDesktopUa || androidTablet || tabletUa || largeTouch;
};

export const isPhoneLikeDevice = () => {
    const { ua, maxTouchPoints, hasCoarsePointer, isLargeViewport } = getDeviceSignals();
    const phoneUa = /iPhone|iPod|Windows Phone/i.test(ua) || (/Android/i.test(ua) && /Mobile/i.test(ua));

    return phoneUa || (maxTouchPoints > 0 && hasCoarsePointer && !isLargeViewport);
};

export const isRealDesktopHoverDevice = () => {
    const { ua, maxTouchPoints, hasTouch, hasHoverMouse } = getDeviceSignals();
    const mobileOrTabletUa = /Android|iPhone|iPad|iPod|Mobile|Tablet|Silk|Kindle|PlayBook|Windows Phone/i.test(ua);

    return hasHoverMouse && maxTouchPoints === 0 && !hasTouch && !mobileOrTabletUa && !isTabletLikeDevice();
};
