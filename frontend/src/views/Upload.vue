<template>
  <div class="upload">
    <h1>Upload page</h1>
    <div v-if="!file">
      <h2>Select an log file</h2>
      <input type="file" @change="onFileChange" />
    </div>
    <div v-else>
      <button @click="submitFile">Send file</button>
      <button @click="removeFile">Remove file</button>
    </div>
  </div>
</template>

<script>
export default {
  name: "Upload",
  data() {
    return {
      file: null
    };
  },
  methods: {
    onFileChange(event) {
      console.log(event.target);
      console.log(event.target.files);

      this.file = event.target.files[0];
    },
    async submitFile() {
      const form = new FormData();
      form.append("file", this.file);

      const res = await fetch("/uploadLog", {
        method: "POST",
        body: form
      });
      const data = await res.json();
      console.log(data);
    },
    removeFile() {
      this.file = null;
    }
  }
};
</script>
