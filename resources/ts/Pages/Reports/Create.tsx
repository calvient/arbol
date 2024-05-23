import {Button, FormControl, FormLabel, Input, Textarea, VStack, Text} from '@calvient/decal';
import Layout from '../../Components/Layout.tsx';
import {useForm} from '@inertiajs/react';

const Create = () => {
  const {data, setData, post, processing, errors} = useForm({
    name: '',
    description: '',
  });

  function submit(e: React.FormEvent<HTMLFormElement>) {
    e.preventDefault();
    post('/arbol/reports');
  }

  return (
    <form onSubmit={submit}>
      <VStack spacing={4}>
        <FormControl isRequired>
          <FormLabel>Report Name</FormLabel>
          <Input value={data.name} onChange={(e) => setData('name', e.target.value)} />
          {errors.name && (
            <Text color={'red'} fontSize={'sm'} mt={2}>
              {errors.name}
            </Text>
          )}
        </FormControl>

        <FormControl>
          <FormLabel>Report Description</FormLabel>
          <Textarea
            value={data.description}
            onChange={(e) => setData('description', e.target.value)}
          />
          {errors.description && (
            <Text color={'red'} fontSize={'sm'} mt={2}>
              {errors.description}
            </Text>
          )}
        </FormControl>

        <Button type={'submit'} isLoading={processing} colorScheme={'blue'}>
          Create Report
        </Button>
      </VStack>
    </form>
  );
};

Create.layout = (page: React.ReactElement) => <Layout children={page} />;

export default Create;
