import assert from 'node:assert/strict';
import {
    DEFAULT_CONFIG,
    shouldBrake,
    calculateKineticImpulse,
    isPrecisionTrackpad,
    canScrollInDirection,
    findScrollableTarget,
} from '../../resources/js/Utils/kineticScroll.js';

console.log('🧪 Starting Kinetic Scroll Engine Unit Test Suite...\n');

// -------------------------------------------------------------
// Test 1: Single Tick Base Impulse
// -------------------------------------------------------------
{
    const res = calculateKineticImpulse(100, -1, 0, DEFAULT_CONFIG);
    assert.equal(res.flicks, 0, 'First isolated tick should have 0 rapid flicks');
    assert.equal(res.multiplier, 1.0, 'Multiplier should be 1.0 on first tick');
    assert.equal(res.impulse, DEFAULT_CONFIG.baseStep, `Base impulse should equal baseStep (${DEFAULT_CONFIG.baseStep}px)`);
    console.log(`✔ Test 1 Passed: Single tick produces cushioned base displacement (~${DEFAULT_CONFIG.baseStep}px impulse)`);
}

// -------------------------------------------------------------
// Test 2: Rapid Flick Velocity Compounding
// -------------------------------------------------------------
{
    // Simulate 3 rapid flicks spaced 100ms apart (< 280ms threshold)
    let flicks = 0;
    let totalVelocity = 0;

    for (let i = 0; i < 3; i++) {
        const res = calculateKineticImpulse(100, 100, flicks, DEFAULT_CONFIG);
        flicks = res.flicks;
        totalVelocity += res.impulse;
    }

    assert.equal(flicks, 3, 'Consecutive flicks counter should reach 3');
    // Multiplier for 3 flicks: 1 + 3 * 0.5 = 2.5x.
    // Ticks: 1st tick = 38 * 1.5 = 57, 2nd = 38 * 2.0 = 76, 3rd = 38 * 2.5 = 95.
    // Total = 228px.
    assert.ok(totalVelocity > 3 * DEFAULT_CONFIG.baseStep, 'Rapid flicks should compound velocity significantly higher than linear');
    console.log(`✔ Test 2 Passed: 3 rapid flicks compound velocity to ${totalVelocity}px (> ${3 * DEFAULT_CONFIG.baseStep}px)`);
}

// -------------------------------------------------------------
// Test 3: Spaced Flick Reset (Slow Scrolling)
// -------------------------------------------------------------
{
    // Simulate flicking slowly spaced 500ms apart (> 280ms threshold)
    const res1 = calculateKineticImpulse(100, 100, 2, DEFAULT_CONFIG); // rapid
    assert.equal(res1.flicks, 3);

    const res2 = calculateKineticImpulse(100, 500, res1.flicks, DEFAULT_CONFIG); // slow
    assert.equal(res2.flicks, 0, 'Slow flick after pause should reset flick counter to 0');
    assert.equal(res2.multiplier, 1.0, 'Multiplier should reset to 1.0');
    console.log('✔ Test 3 Passed: Slow scroll intervals (> 280ms) cleanly reset acceleration multiplier');
}

// -------------------------------------------------------------
// Test 4: Directional Hard Braking
// -------------------------------------------------------------
{
    assert.equal(shouldBrake(120, -100), true, 'Downward momentum (120) + Upward wheel (-100) must trigger brake');
    assert.equal(shouldBrake(-80, 100), true, 'Upward momentum (-80) + Downward wheel (100) must trigger brake');
    assert.equal(shouldBrake(100, 100), false, 'Same direction down must not brake');
    assert.equal(shouldBrake(-100, -100), false, 'Same direction up must not brake');
    assert.equal(shouldBrake(0, 100), false, 'At rest must not brake');
    console.log('✔ Test 4 Passed: Directional hard braking instantly detects opposite impulses');
}

// -------------------------------------------------------------
// Test 5: Precision Trackpad Detection
// -------------------------------------------------------------
{
    // Notched mouse wheel: deltaMode !== 0 or integer delta 100
    const mouseWheel1 = { deltaMode: 1, deltaY: 3 }; // Line mode
    const mouseWheel2 = { deltaMode: 0, deltaY: 100 }; // Standard notch
    const mouseWheel3 = { deltaMode: 0, deltaY: -120 }; // Standard notch reverse

    assert.equal(isPrecisionTrackpad(mouseWheel1), false, 'Mouse wheel line mode is not trackpad');
    assert.equal(isPrecisionTrackpad(mouseWheel2), false, 'Mouse wheel 100px integer is not trackpad');
    assert.equal(isPrecisionTrackpad(mouseWheel3), false, 'Mouse wheel -120px integer is not trackpad');

    // Laptop Precision Trackpad: deltaMode 0, small fractional or continuous stream
    const trackpad1 = { deltaMode: 0, deltaY: 2.34 };
    const trackpad2 = { deltaMode: 0, deltaY: -8.5 };

    assert.equal(isPrecisionTrackpad(trackpad1), true, 'Fractional delta is precision trackpad');
    assert.equal(isPrecisionTrackpad(trackpad2), true, 'Fractional delta is precision trackpad');
    console.log('✔ Test 5 Passed: Precision trackpads are accurately distinguished from mouse wheels');
}

// -------------------------------------------------------------
// Test 6: Container Resolution & Boundary Headroom
// -------------------------------------------------------------
{
    // Mock Element
    class MockElement {
        constructor(style, scrollHeight, clientHeight, scrollTop) {
            this.style = style;
            this.scrollHeight = scrollHeight;
            this.clientHeight = clientHeight;
            this.scrollTop = scrollTop;
            this.parentElement = null;
            this.isConnected = true;
        }
    }

    // Set up mock window and getComputedStyle
    globalThis.Element = MockElement;
    globalThis.window = {
        scrollY: 0,
        innerHeight: 800,
        getComputedStyle: (el) => el.style,
    };
    globalThis.document = {
        documentElement: {
            scrollHeight: 2000,
            scrollTop: 0,
        },
        body: {},
    };

    // Scenario A: Inner container has overflow-y: auto with room to scroll down
    const innerPanel = new MockElement({ overflowY: 'auto' }, 1500, 400, 50);
    const childNode = new MockElement({}, 200, 200, 0);
    childNode.parentElement = innerPanel;

    const targetDown = findScrollableTarget(childNode, 100);
    assert.equal(targetDown, innerPanel, 'Target resolver must select the scrollable innerPanel');

    // Scenario B: Inner container has hit bottom (scrollTop = 1100, maxScroll = 1100)
    innerPanel.scrollTop = 1100;
    const targetAtBottom = findScrollableTarget(childNode, 100);
    assert.equal(targetAtBottom, window, 'When inner panel hits bottom, resolver should fall through to window');

    // Scenario C: Inner container has room to scroll UP from bottom
    const targetScrollUp = findScrollableTarget(childNode, -100);
    assert.equal(targetScrollUp, innerPanel, 'Inner panel at bottom can scroll UP, so it must capture upward wheel');

    console.log('✔ Test 6 Passed: Dynamic target resolution correctly identifies nested panels vs window fallback');
}

console.log('\n🎉 ALL 6 KINETIC SCROLL UNIT TESTS PASSED CLEANLY!\n');
