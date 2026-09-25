import assert from 'node:assert/strict';

console.log('🧪 Starting Branch Findings Grouping Unit Test Suite...\n');

// Mock Category Config
const branchCategoryConfig = [
    { id: 'bgp', name: 'Network & BGP Intelligence' },
    { id: 'threat', name: 'Threat & Reputation Intelligence' },
    { id: 'geo', name: 'Geolocation & Routing Infrastructure' },
    { id: 'ports', name: 'Host & Port Fingerprinting' },
    { id: 'hardware', name: 'Hardware & Wireless Intelligence' },
];

function groupFindings(findingsList, showHitsOnly = false, selectedCategory = 'all') {
    let sourceList = findingsList;
    if (showHitsOnly) {
        sourceList = sourceList.filter(f => f.status === 'found');
    }

    const groups = branchCategoryConfig.map(branch => {
        const findings = sourceList.filter(f => f.category === branch.id);
        const verifiedHits = findings.filter(f => f.status === 'found').length;
        return {
            ...branch,
            findings,
            totalCount: findings.length,
            verifiedHits,
            isClean: findings.length === 0 || (branch.id === 'threat' && verifiedHits === 0),
        };
    });

    const knownIds = new Set(branchCategoryConfig.map(b => b.id));
    const extraFindings = sourceList.filter(f => !f.category || !knownIds.has(f.category));
    if (extraFindings.length > 0) {
        groups.push({
            id: 'other',
            name: 'Supplementary Intelligence Vectors',
            findings: extraFindings,
            totalCount: extraFindings.length,
            verifiedHits: extraFindings.filter(f => f.status === 'found').length,
            isClean: false,
        });
    }

    if (selectedCategory !== 'all') {
        return groups.filter(g => g.id === selectedCategory);
    }

    return groups;
}

// -------------------------------------------------------------
// Test 1: Partitioning Mixed Findings into 5 Branches
// -------------------------------------------------------------
{
    const sampleFindings = [
        { id: 1, platform: 'BGP View', category: 'bgp', status: 'found' },
        { id: 2, platform: 'PeeringDB', category: 'bgp', status: 'info' },
        { id: 3, platform: 'IPQualityScore', category: 'threat', status: 'found' },
        { id: 4, platform: 'Blocklist.de', category: 'threat', status: 'clean' },
        { id: 5, platform: 'MaxMind Geo', category: 'geo', status: 'found' },
        { id: 6, platform: 'Shodan', category: 'ports', status: 'found' },
        { id: 7, platform: 'Wireshark ARP', category: 'hardware', status: 'found' },
        { id: 8, platform: 'Custom Probe', category: 'unknown_vector', status: 'info' },
    ];

    const groups = groupFindings(sampleFindings, false, 'all');

    assert.equal(groups.length, 6, 'Should have 5 standard branches + 1 supplementary branch');
    
    const bgpGroup = groups.find(g => g.id === 'bgp');
    assert.equal(bgpGroup.findings.length, 2, 'BGP should have 2 findings');
    assert.equal(bgpGroup.verifiedHits, 1, 'BGP should have 1 verified hit');

    const threatGroup = groups.find(g => g.id === 'threat');
    assert.equal(threatGroup.findings.length, 2, 'Threat should have 2 findings');

    const extraGroup = groups.find(g => g.id === 'other');
    assert.ok(extraGroup, 'Supplementary vector should catch unknown categories');
    assert.equal(extraGroup.findings.length, 1);

    console.log('✔ Test 1 Passed: Mixed findings successfully partitioned across branch containers');
}

// -------------------------------------------------------------
// Test 2: Clean Verification State for 0-Hit Branches
// -------------------------------------------------------------
{
    const sampleFindings = [
        { id: 1, platform: 'MaxMind Geo', category: 'geo', status: 'found' },
    ];

    const groups = groupFindings(sampleFindings, false, 'all');

    const threatGroup = groups.find(g => g.id === 'threat');
    assert.equal(threatGroup.totalCount, 0, 'Threat branch has 0 findings');
    assert.equal(threatGroup.isClean, true, 'isClean should be true for 0 findings');

    const hardwareGroup = groups.find(g => g.id === 'hardware');
    assert.equal(hardwareGroup.totalCount, 0);
    assert.equal(hardwareGroup.isClean, true);

    const geoGroup = groups.find(g => g.id === 'geo');
    assert.equal(geoGroup.totalCount, 1);
    assert.equal(geoGroup.isClean, false);

    console.log('✔ Test 2 Passed: Empty/0-hit branches correctly identified for Clean verification cards');
}

// -------------------------------------------------------------
// Test 3: Verified Hits Only Filter
// -------------------------------------------------------------
{
    const sampleFindings = [
        { id: 1, platform: 'BGP View', category: 'bgp', status: 'found' },
        { id: 2, platform: 'BGP Tools', category: 'bgp', status: 'info' },
    ];

    const allGroups = groupFindings(sampleFindings, false, 'all');
    assert.equal(allGroups.find(g => g.id === 'bgp').findings.length, 2);

    const hitsOnlyGroups = groupFindings(sampleFindings, true, 'all');
    assert.equal(hitsOnlyGroups.find(g => g.id === 'bgp').findings.length, 1);

    console.log('✔ Test 3 Passed: Hits-only toggle correctly filters findings within each branch');
}

console.log('\n🎉 ALL BRANCH FINDINGS GROUPING TESTS PASSED!\n');
