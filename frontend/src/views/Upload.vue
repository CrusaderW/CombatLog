<template>
  <div class="upload">
    <h1>Upload page</h1>
    <el-row :gutter="20">
      <el-col :span="10" :offset="7">
        <div v-if="fights" style="margin-top: 15px;">
          <div v-for="fight in fights" v-bind:key="fight._id">
            <fight-card :fight="fight" :key="fight._id" />
          </div>
          <el-button size="small" type="primary" @click="saveFights">Save Fights</el-button>
        </div>

        <div v-show="!fights" style="margin-top: 15px;">
          <div>
            <el-input v-model="username" placeholder="username"></el-input>
          </div>
          <h2>Select an log file</h2>
          <el-upload
            action="tmp"
            :on-remove="removeFile"
            :on-change="onFileChange"
            :auto-upload="false"
          >
            <el-button slot="trigger" size="small" type="primary">Choose log file</el-button>
            <el-button
              v-show="file"
              style="margin-left: 15px;"
              size="small"
              type="success"
              @click="submitFile"
            >Upload</el-button>
          </el-upload>
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
  name: "Upload",
  components: {
    FightCard
  },
  data() {
    return {
      file: null,
      username: "",
      campaigns: ["PvP Training", "Trial of Arkon EU", "Trial of Gaea EU"],
      fights: null,
      locations: {}
    };
  },
  methods: {
    onFileChange(file) {
      this.file = file.raw;
    },
    async submitFile() {
      const form = new FormData();
      form.append("file", this.file);
      form.append("username", this.username);
      try {
        const res = await fetch("/uploadLog", {
          method: "POST",
          body: form
        });

        this.fights = await res.json();
        this.$analytics.trackEvent("UploadLogBtn", "Upload", this.file.name);
      } catch (err) {
        this.$analytics.trackEvent(
          "UploadLogBtn",
          "UploadFailed",
          this.file.name
        );
      }
      this.fights.forEach(fight => {
        this.locations[fight._id] = fight.location;
      });
      console.log(this.fights);
    },
    async saveFights() {
      try {
        const res = await fetch("/saveFights", {
          method: "POST",
          headers: {
            "content-type": "application/json"
          },
          body: JSON.stringify({
            locations: this.fights.map(fight => ({
              location: fight.location,
              _id: fight._id
            }))
          })
        });
        const data = await res.json();
        this.$analytics.trackEvent("SaveFightsBtn", "Save");
        console.log(data);
      } catch (err) {
        this.$analytics.trackEvent("SaveFightsBtn", "SaveFailed");
      }
    },
    removeFile() {
      this.file = null;
    }
  }
};
</script>
