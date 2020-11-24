<template>
  <div class="fights">
    <h1>Your Fights</h1>
    <fight-logs-visualization />
  </div>
</template>
<script>
import FightLogsVisualization from "../components/FightLogsVisualization.vue";
export default {
  components: { FightLogsVisualization }
};
</script>
