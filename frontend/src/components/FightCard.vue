<template>
  <el-card style="margin-bottom: 15px;">
    <div style="margin-bottom: 15px">From {{fight.datetimeStart}}</div>
    <div style="margin-bottom: 15px">To {{fight.datetimeEnd}}</div>
    <el-select class="location_input" v-model="fight.location.campaign" placeholder="campaign">
      <el-option v-for="campaign in campaigns" :key="campaign" :label="campaign" :value="campaign"></el-option>
    </el-select>
    <el-input class="location_input" v-model="fight.location.zone" placeholder="Zone"></el-input>
    <el-input class="location_input" v-model="fight.location.POI" placeholder="POI"></el-input>
    <el-button style="margin-top: 15px" type="danger" @click="deleteFight">Delete</el-button>
    <el-button style="margin-left: 15px" @click="updateLocation">Update</el-button>
    <el-button type="primary" style="margin-left: 15px" @click="selectFight">Select</el-button>
  </el-card>
</template>

<script>
export default {
  props: {
    fight: Object
  },
  data() {
    return {
      campaigns: ["PvP Training", "Trial of Arkon EU", "Trial of Gaea EU"]
    };
  },
  methods: {
    selectFight() {
      this.$emit("select-fight", this.fight);
    },
    async updateLocation() {
      try {
        this.fight.location = await (await fetch("/updateLocation", {
          method: "POST",
          headers: {
            "content-type": "application/json"
          },
          body: JSON.stringify({
            _id: this.fight._id,
            location: this.fight.location
          })
        })).json();
        this.$analytics.trackEvent("UpdateLocation", "update", this.fight._id);
      } catch (err) {
        this.$analytics.trackEvent(
          "UpdateLocation",
          "updateFailed",
          this.fight._id
        );
      }
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
