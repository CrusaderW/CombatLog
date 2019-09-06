<template>
  <el-card style="margin-bottom: 15px;">
    <div style="margin-bottom: 15px">From {{fight.datetimeStart}}</div>
    <div style="margin-bottom: 15px">To {{fight.datetimeEnd}}</div>
    <el-select class="location_input" v-model="fight.location.campaign" placeholder="campaign">
      <el-option v-for="campaign in campaigns" :key="campaign" :label="campaign" :value="campaign"></el-option>
    </el-select>
    <el-input class="location_input" v-model="fight.location.zone" placeholder="Zone"></el-input>
    <el-input class="location_input" v-model="fight.location.POI" placeholder="POI"></el-input>
    <el-button style="margin-top: 15px" @click="updateLocation">Update</el-button>
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
    async updateLocation() {
      this.fight.location = await fetch(
        "http://localhost:8080/updateLocation",
        {
          method: "POST",
          headers: {
            "content-type": "application/json"
          },
          body: JSON.stringify({
            _id: this.fight._id,
            location: this.fight.location
          })
        }
      );
    }
  }
};
</script>