function createChallenge(data) {
  if (DB_AVAILABLE) {
    fetch('../Controllers/ChallengesController.php?action=create', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) }).then(() => location.reload()).catch(() => alert('Error creating challenge.'));
  } else {
    const challenges = JSON.parse(localStorage.getItem('challenges') || '[]');
    data.id = challenges.length + 1;
    data.createdAt = new Date().toISOString();
    challenges.push(data);
    localStorage.setItem('challenges', JSON.stringify(challenges));
  }
}

function updateChallenge(id, data) {
  if (DB_AVAILABLE) {
    fetch('../Controllers/ChallengesController.php?action=update&id=' + id, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) }).then(() => location.reload()).catch(() => alert('Error updating challenge.'));
  } else {
    const challenges = JSON.parse(localStorage.getItem('challenges') || '[]');
    const index = challenges.findIndex(c => c.id == id);
    if (index !== -1) {
      challenges[index] = { ...challenges[index], ...data };
      localStorage.setItem('challenges', JSON.stringify(challenges));
    }
  }
}

function deleteChallenge(id) {
  if (DB_AVAILABLE) {
    fetch('../Controllers/ChallengesController.php?action=delete&id=' + id).then(() => location.reload()).catch(() => alert('Error deleting challenge.'));
  } else {
    const challenges = JSON.parse(localStorage.getItem('challenges') || '[]');
    const filtered = challenges.filter(c => c.id != id);
    localStorage.setItem('challenges', JSON.stringify(filtered));
  }
}

function getChallenges() {
  if (DB_AVAILABLE) {
    return fetch('../Controllers/ChallengesController.php?action=all').then(res => res.json()).then(data => data.challenges).catch(() => []);
  } else {
    return JSON.parse(localStorage.getItem('challenges') || '[]');
  }
}

function createReward(data) {
  if (DB_AVAILABLE) {
    fetch('../Controllers/RewardsController.php?action=create', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) }).then(() => location.reload()).catch(() => alert('Error creating reward.'));
  } else {
    const rewards = JSON.parse(localStorage.getItem('rewards') || '[]');
    data.id = rewards.length + 1;
    data.createdAt = new Date().toISOString();
    rewards.push(data);
    localStorage.setItem('rewards', JSON.stringify(rewards));
  }
}

function updateReward(id, data) {
  if (DB_AVAILABLE) {
    fetch('../Controllers/RewardsController.php?action=update&id=' + id, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) }).then(() => location.reload()).catch(() => alert('Error updating reward.'));
  } else {
    const rewards = JSON.parse(localStorage.getItem('rewards') || '[]');
    const index = rewards.findIndex(r => r.id == id);
    if (index !== -1) {
      rewards[index] = { ...rewards[index], ...data };
      localStorage.setItem('rewards', JSON.stringify(rewards));
    }
  }
}

function deleteReward(id) {
  if (DB_AVAILABLE) {
    fetch('../Controllers/RewardsController.php?action=delete&id=' + id).then(() => location.reload()).catch(() => alert('Error deleting reward.'));
  } else {
    const rewards = JSON.parse(localStorage.getItem('rewards') || '[]');
    const filtered = rewards.filter(r => r.id != id);
    localStorage.setItem('rewards', JSON.stringify(filtered));
  }
}

function getRewards() {
  if (DB_AVAILABLE) {
    return fetch('../Controllers/RewardsController.php?action=getAll').then(res => res.json()).then(data => data.rewards).catch(() => []);
  } else {
    return JSON.parse(localStorage.getItem('rewards') || '[]');
  }
}

function getPoints(studentID) {
  if (DB_AVAILABLE) {
    return fetch('../Controllers/PointsController.php?action=getBalance').then(res => res.json()).then(data => data.balance).catch(() => 0);
  } else {
    const points = JSON.parse(localStorage.getItem('points') || '[]');
    return points.find(p => p.studentId == studentID) || { balance: 0, history: [] };
  }
}