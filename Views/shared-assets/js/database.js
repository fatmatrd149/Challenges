const challenges = JSON.parse(localStorage.getItem('challenges') || '[]');
const rewards = JSON.parse(localStorage.getItem('rewards') || '[]');
const points = JSON.parse(localStorage.getItem('points') || '[]');

function saveChallenges(data) {
  localStorage.setItem('challenges', JSON.stringify(data));
  if (DB_AVAILABLE) {
    fetch('../Controllers/ChallengesController.php?action=all').then(res => res.json()).then(dbData => {}).catch(() => {});
  }
}

function saveRewards(data) {
  localStorage.setItem('rewards', JSON.stringify(data));
  if (DB_AVAILABLE) {
    fetch('../Controllers/RewardsController.php?action=getAll').then(res => res.json()).then(dbData => {}).catch(() => {});
  }
}

function savePoints(data) {
  localStorage.setItem('points', JSON.stringify(data));
  if (DB_AVAILABLE) {
    fetch('../Controllers/PointsController.php?action=getBalance').then(res => res.json()).catch(() => {});
  }
}

function getChallenges() {
  if (DB_AVAILABLE) {
    return fetch('../Controllers/ChallengesController.php?action=all').then(res => res.json()).then(data => data.challenges || challenges).catch(() => challenges);
  } else {
    return challenges;
  }
}

function getRewards() {
  if (DB_AVAILABLE) {
    return fetch('../Controllers/RewardsController.php?action=getAll').then(res => res.json()).then(data => data.rewards || rewards).catch(() => rewards);
  } else {
    return rewards;
  }
}

function getPoints(studentID) {
  if (DB_AVAILABLE) {
    return fetch('../Controllers/PointsController.php?action=getBalance').then(res => res.json()).then(data => data.balance).catch(() => 0);
  } else {
    return points.find(p => p.studentId == studentID) || { balance: 0, history: [] };
  }
}