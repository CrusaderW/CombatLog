<template>
  <el-card style="margin-bottom: 15px;">
    <div style="margin-bottom: 15px">From {{fight.datetimeStart}}</div>
    <div style="margin-bottom: 15px">To {{fight.datetimeEnd}}</div>
    <el-select class="location_input" v-model="fight.location.campaign" placeholder="campaign">
      <el-option v-for="campaign in campaigns" :key="campaign" :label="campaign" :value="campaign"></el-option>
    </el-select>
    <el-input class="location_input" v-model="fight.location.zone" placeholder="Zone"></el-input>
    <el-input class="location_input" v-model="fight.location.POI" placeholder="POI"></el-input>
    <div v-if="selectable">
      <el-button style="margin-top: 15px" type="danger" @click="deleteFight">Delete</el-button>
      <el-button style="margin-left: 15px" @click="updateLocation">Update</el-button>
      <el-button type="primary" style="margin-left: 15px" @click="selectFight">Select</el-button>
    </div>
  </el-card>
</template>

<script>
export default {
  props: {
    fight: Object,
    selectable: Boolean
  },
  data() {
    return {
      campaigns: ["PvP Training", "Trial of Yaga EU"]
    };
  },
  methods: {
    selectFight() {
      this.$emit("select-fight", this.fight);
    },
    updateLocation() {
      this.$emit("update-fight-location", this.fight);
    },
    async deleteFight() {
      try {
        const { success, err } = await (await fetch("/deleteFight", {
          method: "DELETE",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ _id: this.fight._id })
        })).json();
        if (success) {
          this.$analytics.trackEvent("DeleteFight", "delete", this.fight._id);
          this.$emit("delete-fight", this.fight._id);
        } else {
          throw new Error(err);
        }
      } catch (err) {
        this.$analytics.trackEvent(
          "DeleteFight",
          "deleteFailed",
          this.fight._id
        );
      }
    }
  }
};
</script>
