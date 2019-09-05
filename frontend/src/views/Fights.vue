<template>
  <div>
    <h1>Fights page</h1>
    <el-row :gutter="20">
      <el-col :span="10" :offset="7">
        <div v-if="fights" style="margin-top: 15px;">
          <div v-for="fight in fights" :key="fight._id">
            <fight-card :fight="fight" :key="fight._id" />
          </div>
        </div>
      </el-col>
    </el-row>
  </div>
</template>

<style>
.location_input {
  width: 150px;
  margin-left: 15px;
}
</style>

<script>
import FightCard from "../components/FightCard.vue";

export default {
  name: "fightsPage",
  components: {
    FightCard
  },
  data() {
    return {
      fights: null
    };
  },
  async mounted() {
    this.fights = await (await fetch("/lastFights")).json();
  },
  updateLocation() {}
};
</script>